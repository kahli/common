<?php

namespace Drupal\service\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\service\ServiceInterface;
use Drupal\service\Entity\ServiceType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Entity class for Services.
 *
 * @ContentEntityType(
 *   id = "service",
 *   label = @Translation("Service"),
 *   label_singular = @Translation("service"),
 *   label_plural = @Translation("services"),
 *   label_count = @PluralTranslation(
 *     singular = "@count service",
 *     plural = "@count services"
 *   ),
 *   bundle_label = @Translation("Service Type"),
 *   handlers = {
 *     "list_builder" = "Drupal\service\ServiceListBuilder",
 *     "storage" = "Drupal\service\ServiceStorage",
 *     "access" = "Drupal\service\ServiceAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\service\Form\ServiceForm"
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "service",
 *   revision_table = "service_revision",
 *   admin_permission = "administer services",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "owner" = "manager",
 *   },
 *   has_notes = "true",
 *   bundle_entity_type = "service_type",
 *   field_ui_base_route = "entity.service_type.edit_form",
 *   links = {
 *     "collection" = "/service",
 *     "canonical" = "/service/{service}",
 *     "edit-form" = "/service/{service}/edit"
 *   }
 * )
 */
class Service extends ContentEntityBase implements ServiceInterface, EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback('\Drupal\service\Entity\Service::createLabel')
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active?'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('\Drupal\service\Entity\Service::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
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

    $fields['service'] = BaseFieldDefinition::create('service_reference')
      ->setLabel(t('Service'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'service')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['recipients'] = BaseFieldDefinition::create('entity_reference')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setLabel(t('Recipients'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return ServiceType::load($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $type = $this->getType();
    if ($type->get('default_label')) {
      $this->label = $this->applyTokens($type->get('default_label'));
    }
  }

  /**
   * Create the label.
   */
  public static function createLabel(ServiceInterface $service, FieldDefinitionInterface $fieldDefinition) {
    $token_service = \Drupal::token();

    $type = $service->getType();
    if ($type->get('default_label')) {
      return $token_service->replace($type->get('default_label'), ['service' => $service]);
    }

    return '';
  }

  /**
   * Apply tokens to a string.
   */
  protected function applyTokens($string, BubbleableMetadata $bubbleable_metadata = NULL) {
    $token_service = \Drupal::token();
    $return  = $token_service->replace($string, ['service' => $this], [], $bubbleable_metadata);
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getManager() {
    return $this->manager->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getManagerId() {
    return $this->manager->target_id;
  }

  /**
   * Get the recipients.
   *
   * @return \Drupal\user\UserInterface[]
   *   A list of user entities of the recipients.
   */
  public function getRecipients() {
    return $this->recipients->getReferencedEntities();
  }

  /**
   * Get the main recipient.
   *
   * @return \Drupal\user\UserInterface
   *   The main recipient
   */
  public function getMainRecipient() {
    return $this->recipients[0]->entity;
  }

  /**
   * Get the recipient ids.
   *
   * @return string[]|int[]
   */
  public function getRecipientIds() {
    $ids = [];
    foreach ($this->recipients as $item) {
      $ids[] = $item->target_id;
    }
    return $ids;
  }
}

