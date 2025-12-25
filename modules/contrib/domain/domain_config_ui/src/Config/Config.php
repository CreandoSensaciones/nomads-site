<?php

namespace Drupal\domain_config_ui\Config;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Config as CoreConfig;
use Drupal\domain_config_ui\DomainConfigUIManager;

/**
 * Extend core Config class to save domain specific configuration.
 */
class Config extends CoreConfig {

  /**
   * The configuration values that have been set.
   *
   * @var array
   */
  protected array $updatedData = [];

  /**
   * The Domain config UI manager.
   *
   * @var \Drupal\domain_config_ui\DomainConfigUIManager
   */
  protected $domainConfigUIManager;

  /**
   * Set the Domain config UI manager.
   *
   * @param \Drupal\domain_config_ui\DomainConfigUIManager $domain_config_ui_manager
   *   The Domain config UI manager.
   */
  public function setDomainConfigUiManager(DomainConfigUIManager $domain_config_ui_manager) {
    $this->domainConfigUIManager = $domain_config_ui_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    parent::set($key, $value);
    $parts = explode('.', $key);
    $value = $this->castSafeStrings($value);
    NestedArray::setValue($this->updatedData, $parts, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data) {
    parent::setData($data);
    $this->updatedData = $this->data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save($has_trusted_data = FALSE) {
    // Remember original config name.
    $originalName = $this->name;

    try {
      // Get domain config name for saving.
      $domainConfigName = $this->getDomainConfigName();

      // If config is new and we are saving domain specific configuration,
      // save with original name so there is always a default configuration.
      if ($this->isNew && $domainConfigName !== $originalName) {
        parent::save($has_trusted_data);
      }

      // If there is a schema for this configuration object, cast all values to
      // conform to the schema.
      // Need to be duplicated here as it will not be done in the parent core
      // Config class as the schema does not exist for the domain-related
      // configuration name.
      if (!$has_trusted_data) {
        if ($this->typedConfigManager->hasConfigSchema($this->name)) {
          // Ensure that the schema wrapper has the latest data.
          $this->schemaWrapper = NULL;
          $this->updatedData = $this->castValue(NULL, $this->updatedData);
        }
        else {
          foreach ($this->updatedData as $key => $value) {
            $this->validateValue($key, $value);
          }
        }
      }

      // Merge the updated data into the overridden values before saving.
      // This will allow handling configurations managed with multiple forms.
      // This will allow partial overriding of values on a per-form basis.
      if (empty($this->moduleOverrides)) {
        $this->data = $this->updatedData;
      }
      else {
        $this->data = NestedArray::mergeDeepArray(
          [$this->moduleOverrides, $this->updatedData], TRUE
        );
      }

      // Switch to use domain config name and save.
      $this->name = $domainConfigName;
      parent::save($has_trusted_data);
    }
    catch (\Exception $e) {
      // Reset back to original config name if save fails and re-throw.
      $this->name = $originalName;
      throw $e;
    }

    // Reset back to original config name after saving.
    $this->name = $originalName;

    return $this;
  }

  /**
   * Get the domain config name.
   */
  protected function getDomainConfigName() {
    // Return selected config name.
    return $this->domainConfigUIManager->getSelectedConfigName($this->name);
  }

}
