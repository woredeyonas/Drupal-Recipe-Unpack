<?php

namespace Drupal\Recipe\Unpack\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Drupal\Recipe\Unpack\Command\UnpackCommand;

/**
 * List of all commands provided by this package.
 *
 * @internal
 */
class CommandProvider implements CommandProviderCapability {

  /**
   * {@inheritdoc}
   */
  public function getCommands() {
    return [new UnpackCommand()];
  }

}
