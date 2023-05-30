<?php

namespace Drupal\Recipe\Unpack;

use Composer\Factory;
use Composer\Config\JsonConfigSource;
use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Composer\Package\Version\VersionParser;
use Drupal\Recipe\Unpack\Operation;
use Composer\Json\JsonManipulator;
use Composer\Repository\RepositoryManager;
use Throwable;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Plugin\PluginInterface;

/**
 * The "drupal_recipe:unpack" command class.
 *
 * @internal
 */
class UnpackCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('drupal_recipe:unpack')
      ->setAliases(['unpack'])
      ->setDescription('Unpack module dependencies to the project package list.')
      ->setDefinition([
        new InputArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Installed packages to unpack.'),
        new InputOption('sort-packages', null, InputOption::VALUE_NONE, 'Sorts packages'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $composer = $this->getComposer();
    $packages = $input->getArgument('packages');
    $lockData = $composer->getLocker()->getLockData();
    $io = $this->getIO();
    $installedRepo = $composer->getRepositoryManager();
    $op = new Operation(true, $input->getOption('sort-packages') || $composer->getConfig()->get('sort-packages'));
    $package = reset($packages);
    if (null === $pkg = $installedRepo->findPackage($package, '*')) {
      $io->writeError(sprintf('<error>Package %s is not installed</>', $package['name']));
      return 1;
    }

    $dev = false;
    foreach ($lockData['packages-dev'] as $p) {
      if ($package === $p['name']) {
          $dev = true;
          break;
      }
    }

    $dependencies = $pkg->getRequires() ?? [];
    foreach ($dependencies as $dependency) {
      $op->addPackage($dependency->getTarget(), $dependency->getConstraint(), false);
    }
    $devDependencies = $pkg->getDevRequires() ?? [];
    foreach ($devDependencies as $devDependency) {
      $op->addPackage($devDependency->getTarget(), $devDependency->getConstraint(), true);
    }

    $this->updateComposer($op, $installedRepo);
    $this->updateLock($package);
    return 0;
  }

  protected function updateComposer(Operation $op, RepositoryManager $installedRepo):void {
    $versionParser = new VersionParser();
    $jsonPath = Factory::getComposerFile();
    $jsonContent = file_get_contents($jsonPath);
    $jsonStored = json_decode($jsonContent, true);
    $jsonManipulator = new JsonManipulator($jsonContent);

    try {
      foreach ($op->getPackages() as $link) {
        $package = $installedRepo->findPackage($link['name'], '*');
        $link['type'] = 'require';
        $link['constraints'] = $link['version'];

        // nothing to do, package is already present in the "require" section
        if (isset($jsonStored['require'][$link['name']])) {
            continue;
        }

        if (isset($jsonStored['require-dev'][$link['name']])) {
            // nothing to do, package is already present in the "require-dev" section
            if ('require-dev' === $link['type']) {
                continue;
            }

            // removes package from "require-dev", because it will be moved to "require"
            // save stored constraint
            $link['constraints'][] = $versionParser->parseConstraints($jsonStored['require-dev'][$link['name']]);
            $jsonManipulator->removeSubNode('require-dev', $link['name']);
        }

        //$constraint = end($link['constraints']);

        if (!$jsonManipulator->addLink($link['type'], $link['name'], $link['constraints'], $op->shouldSort())) {
            throw new \RuntimeException(sprintf('Unable to unpack package "%s".', $link['name']));
        }
    }

    file_put_contents($jsonPath, $jsonManipulator->getContents());
    }
    catch(Throwable $exception) {
      throw $exception;
    }
  }

  protected function updateLock(string $package):void {
    $io = $this->getIO();
    $json = new JsonFile(Factory::getComposerFile());
    $manipulator = new JsonConfigSource($json);
    $locker = $this->getComposer()->getLocker();
    $lockData = $locker->getLockData();
    $manipulator->removeLink('require-dev', $package);
    foreach ($lockData['packages-dev'] as $i => $pkg) {
        if ($package === $pkg['name']) {
            unset($lockData['packages-dev'][$i]);
        }
    }
    $manipulator->removeLink('require', $package);
    foreach ($lockData['packages'] as $i => $pkg) {
        if ($package === $pkg['name']) {
            unset($lockData['packages'][$i]);
        }
    }
    $jsonContent = file_get_contents($json->getPath());
    $lockData['packages'] = array_values($lockData['packages']);
    $lockData['packages-dev'] = array_values($lockData['packages-dev']);
    $lockData['content-hash'] = Locker::getContentHash($jsonContent);
    $lockFile = new JsonFile(substr($json->getPath(), 0, -4).'lock', null, $io);

    // force removal of files under vendor/
    if (version_compare('2.0.0', PluginInterface::PLUGIN_API_VERSION, '>')) {
        $locker = new Locker($io, $lockFile, $this->composer->getRepositoryManager(), $this->composer->getInstallationManager(), $jsonContent);
    } else {
        $locker = new Locker($io, $lockFile, $this->composer->getInstallationManager(), $jsonContent);
    }
    $this->composer->setLocker($locker);
  }
}
