<?php

namespace Drupal\identity\Plugin\IdentityDataType;

use Drupal\entity\BundlePlugin\BundlePluginInterface;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\IdentityMatch;

interface IdentityDataTypeInterface extends BundlePluginInterface {

  /**
   * @param \Drupal\identity\Entity\IdentityData $data
   *
   * @return integer
   */
  public function acquisitionPriority(IdentityData $data);

  /**
   * @param \Drupal\identity\Entity\IdentityData $data
   *
   * @return mixed
   */
  public function findMatches(IdentityData $data);

  /**
   * Work out whether the data supports or opposes
   *
   * @param \Drupal\identity\Entity\IdentityData $data
   *   The data that has been supplied to the acquisition function.
   * @param \Drupal\identity\IdentityMatch $match
   *   The match that has been found by find matches.
   */
  public function supportOrOppose(IdentityData $data, IdentityMatch $match);
}
