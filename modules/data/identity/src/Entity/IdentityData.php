<?php

namespace Drupal\identity\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\identity\Entity\IdentityDataInterface;
use Drupal\identity\Entity\IdentityDataType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\identity\IdentityMatch;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Entity class for IdentityDatas.
 *
 * @ContentEntityType(
 *   id = "identity_data",
 *   label = @Translation("Identity Data"),
 *   label_singular = @Translation("identity data"),
 *   label_plural = @Translation("identity datas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count identity data",
 *     plural = "@count identity data"
 *   ),
 *   bundle_label = @Translation("Identity Data Type"),
 *   handlers = {
 *     "storage" = "Drupal\identity\IdentityDataStorage",
 *     "access" = "Drupal\identity\IdentityDataAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "identity_data",
 *   revision_table = "identity_data_revision",
 *   admin_permission = "administer identity data",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "owner" = "user",
 *   },
 *   has_notes = "true",
 *   bundle_plugin_type = "identity_data_type",
 *   field_ui_base_route = "entity.identity_type.edit_form",
 *   links = {
 *     "canonical" = "/identity/data/{identity_data}",
 *     "edit-form" = "/identity/data/{identity_data}/edit"
 *   }
 * )
 */
class IdentityData extends ContentEntityBase implements IdentityDataInterface, EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * @var \Drupal\identity\Plugin\IdentityDataType\IdentityDataTypeInterface
   */
  protected $_type;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['identity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Identity'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'identity')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'identity_data_source')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['source'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source'))
      ->setSetting('target_type', 'identity_data_source')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['reference'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference'))
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['archived'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Archived'))
      ->setDefaultValue(['value' => FALSE])
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['group'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('Group'));

    return $fields;
  }

  /**
   * Get the data type plugin.
   *
   * @return \Drupal\identity\Plugin\IdentityDataType\IdentityDataTypeInterface
   */
  public function getType() {
    if (!$this->_type) {
      $this->_type = \Drupal::service('plugin.manager.identity_data_type')
        ->createInstance($this->type->value);
    }

    return $this->_type;
  }

  /**
   * Get the identity of this data.
   *
   * @return \Drupal\identity\Entity\Identity
   */
  public function getIdentity() {
    return $this->identity->entity;
  }

  /**
   * Get the acquisition priority of this data.
   *
   * @return integer
   */
  public function acquisitionPriority() {
    return $this->getType()->acquisitionPriority($this);
  }

  /**
   * Find matches for this data.
   *
   * @return \Drupal\identity\IdentityMatch[]
   */
  public function findMatches() {
    return $this->getType()->findMatches($this);
  }

  /**
   * Support or oppose a match.
   *
   * @param \Drupal\identity\IdentityMatch $match
   */
  public function supportOrOppose(IdentityMatch $match) {
    return $this->getType()->supportOrOppose($this, $match);
  }
}

