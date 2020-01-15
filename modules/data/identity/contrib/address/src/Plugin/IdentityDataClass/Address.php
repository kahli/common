<?php

namespace Drupal\identity_address_data\Plugin\IdentityDataClass;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\identity\Entity\IdentityData;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\IdentityMatch;
use Drupal\identity\Plugin\IdentityDataClass\IdentityDataClassBase;

/**
 * Class Address
 *
 * @IdentityDataClass(
 *   id = "address",
 *   label = @Translation("Address"),
 *   plural_label = @Translation("Addresses"),
 *   form_defaults = {
 *     "weight" = 1
 *   }
 * );
 *
 * @package Drupal\identity_address_data\Plugin\IdentityDataClass
 */
class Address extends IdentityDataClassBase {

  const TYPE_MAILING = 'mailing';
  const TYPE_PHYSICAL = 'physical';

  /**
   * {@inheritdoc}
   */
  public function createData($type, $reference, $value = NULL) {
    $data = parent::createData($type, $reference, $value);

    if (is_array($value)) {
      $data->address = $value;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['address'] = BundleFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setCardinality(1)
      ->setSetting('available_countries', ['US' => 'US'])
      ->setSetting('field_overrides', [
        'givenName' => ['override' => 'hidden'],
        'familyName' => ['override' => 'hidden'],
        'organization' => ['override' => 'hidden'],
      ])
      ->setDisplayOptions('view', [
        'type' => 'address_default',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'address',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // @todo: Consider storing geolocation data for better supporting logic.

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function dataLabel(IdentityData $data) {
    $render = $data->address->view([
      'type' => 'address_plain',
      'label' => 'hidden',
    ]);
    return drupal_render($render);
  }

  /**
   * {@inheritdoc}
   */
  public function findMatches(IdentityData $data) {
    /** @var \Drupal\identity\Entity\Query\IdentityDataQueryInterface $query */
    $query = $this->identityDataStorage->getQuery();
    $query->identityDistinct();

    $query->condition('class', $this->pluginId);
    $query->exists('identity');
    $query->sort('identity');
    $query->range(0 , 50);

    $has_personal_name = $has_org_name = $has_admin_local = $enough_data = FALSE;
    if ($data->address->country_code) {
      $query->condition('address.country_code', $data->address->country_code);
    }
    if ($data->address->administrative_area) {
      $has_admin_local = TRUE;
      $query->condition('address.administrative_area', $data->address->administrative_area);
    }
    if ($data->address->locality) {
      $has_admin_local = TRUE;
      $query->condition('address.locality', $data->address->locality);
    }
    if ($data->address->address_line1) {
      $enough_data = $has_admin_local;
      $query->condition('address.address_line1', $data->address->address_line1);
      if ($data->address->address_line2) {
        $query->condition('address.address_line2', $data->address->address_line2);
      }
    }

    if ($data->address->given_name && $data->address->family_name) {
      $query->condition('address.given_name', $data->address->given_name);
      $query->condition('address.family_name', $data->address->family_name);
      $has_personal_name = TRUE;
    }

    if ($data->address->organization) {
      $query->condition('address.organization', $data->address->organization);

      // We do this to stop individuals being found as organizations.
      if (!$has_personal_name) {
        $query->notExists('address.given_name');
        $query->notExists('address.family_name');
      }

      $has_org_name = TRUE;
    }

    if (!$enough_data) {
      return [];
    }
    $matches = [];
    foreach ($this->identityDataStorage->loadMultiple($query->execute()) as $match_data) {
      /** @var IdentityData $match_data */
      if ($match_data->getIdentityId()) {
        $matches[$match_data->getIdentityId()] = new IdentityMatch(
          $data,
          $match_data,
          ($has_org_name || $has_personal_name) ? 100 : 50
        );
      }
    }
    return $matches;
  }

  /**
   * {@inheritdoc}
   */
  public function supportOrOppose(IdentityData $search_data, IdentityMatch $match) {
    $identity = $match->getIdentity();

    /** @var \Drupal\identity\Entity\IdentityData $match_data */
    foreach ($identity->getData($this->pluginId) as $match_data) {
      if (
        $search_data->address->country_code == $match_data->address->country_code &&
        $search_data->address->postal_code == $match_data->address->postal_code
      ) {
        $levels = ['postal_code'];
        $score_inc = 30;

        if (
          $search_data->address->address_line1 == $match_data->address->address_line1
          && $search_data->address->address_line2 == $match_data->address->address_line2
        ) {
          $levels[] = 'street';
          $score_inc = 70;
        }

        if ($search_data->address->given_name || $search_data->address->family_name) {
          if (
            $search_data->address->given_name == $match_data->address->given_name
            && $search_data->address->family_name == $match_data->address->family_name
          ) {
            $levels[] = 'personal_name';
            $score_inc = 100;
          }
        }
        else if (
          $search_data->address->organization
          && $search_data->address->organization == $match_data->address->organization
        ) {
          $levels[] = 'organization_name';
          $score_inc = 100;
        }

        // Once the match is fully supported we return.
        if ($match->supportMatch($search_data, $match_data, $score_inc, $levels)) {
          return;
        }
      }
    }
  }

  public function typeOptions() {
    return [
      static::TYPE_MAILING => new TranslatableMarkup('Mailing'),
      static::TYPE_PHYSICAL => new TranslatableMarkup('Physical'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function possibleMatchSupportLevels(IdentityDataInterface $search_data) {
    $levels = ['postal_code', 'street'];
    if ($search_data->address->given_name) {
      $levels[] = 'personal_name';
    }
    if ($search_data->address->organization) {
      $levels[] = 'organization_name';
    }

    return $levels;
  }
}
