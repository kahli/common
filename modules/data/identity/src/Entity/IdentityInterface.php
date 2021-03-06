<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface IdentityInterface extends ContentEntityInterface {

  /**
   * Get the datas of a certain type.
   *
   * @param $type
   * @param array $filters
   *
   * @return \Drupal\identity\Entity\IdentityData[]|\Drupal\identity\IdentityDataIterator
   *
   * @todo: Always return an iterator.
   */
  public function getData($type, array $filters = []);

  /**
   * Get all datas from the identity.
   *
   * @return \Drupal\identity\Entity\IdentityData[]|\Drupal\identity\IdentityDataIterator
   */
  public function getAllData(array $filters = []);

}
