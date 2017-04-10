<?php

namespace Drupal\groupmenu;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContentType;

/**
 * Group menu configuration overrides.
 */
class GroupMenuConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * The configuration storage.
   *
   * Do not access this directly. Should be accessed through self::getConfig()
   * so that the cache of configurations is used.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $baseStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user's account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * An array of configuration arrays keyed by configuration name.
   *
   * @var array
   */
  protected $configurations;

  /**
   * An array of group types arrays keyed by node type.
   *
   * @var array
   */
  protected $groupTypes;

  /**
   * Constructs the GroupMenuConfigOverrides object.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The configuration storage engine.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(StorageInterface $storage, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->baseStorage = $storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    $node_type_names = array_filter($names, function ($name) {
      return strpos($name, 'node.type') === 0;
    });

    if (!empty($node_type_names)) {
      foreach ($node_type_names as $node_type_name) {
        $current_config = $this->getConfig($node_type_name);

        // We first get a list of all group types where the node type plugin
        // has enabled the setting to show group menu's. With those group
        // types we can get all the group menu content types to look for actual
        // group menu content. Once we have the group menu content, we can
        // check their groups to see if the user has permissions to edit the
        // menu's.
        $group_types = $this->getEnabledGroupMenuTypesByNodeType($current_config['type']);
        if ($group_types && $menus = $this->getUserGroupMenuIdsByGroupTypes($group_types, $this->currentUser)) {
          $overrides[$node_type_name] = [
            'third_party_settings' => [
              'menu_ui' => [
                'available_menus' => array_merge($current_config['third_party_settings']['menu_ui']['available_menus'], $menus),
              ],
            ],
          ];
        }

      }
    }

    return $overrides;
  }

  /**
   * Get all group types where the group menu's are enabled for a node type.
   *
   * @param string $node_type
   *   A node type.
   *
   * @return array
   *   An array of group types with the ID as key and value.
   */
  protected function getEnabledGroupMenuTypesByNodeType($node_type) {
    if (isset($this->groupTypes[$node_type])) {
      return $this->groupTypes[$node_type];
    }

    $plugin_id = 'group_node:' . $node_type;
    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);

    // Get the list of group types to find menu's for.
    $this->groupTypes[$node_type] = [];
    /** @var \Drupal\group\entity\GroupContentTypeInterface $group_content_type */
    foreach ($group_content_types as $group_content_type) {
      if (!empty($group_content_type->getContentPlugin()->getConfiguration()['node_form_group_menu'])) {
        $this->groupTypes[$node_type][$group_content_type->getGroupType()->id()] = $group_content_type->getGroupType()->id();
      }
    }

    return $this->groupTypes[$node_type];
  }

  /**
   * Get a users group menu IDs for a list of group types.
   *
   * @param array $group_types
   *   An array of group types with the ID as key.
   *
   * @return array
   *   An array of menu IDs.
   */
  protected function getUserGroupMenuIdsByGroupTypes(array $group_types, AccountInterface $account) {
    $plugin_id = 'group_menu:menu';
    $group_content_types = $this->entityTypeManager->getStorage('group_content_type')
      ->loadByProperties([
        'content_plugin' => $plugin_id,
        'group_type' => array_keys($group_types),
      ]);

    if (empty($group_content_types)) {
      return [];
    }

    $group_contents = $this->entityTypeManager->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
      ]);

    // Check access and add menu's to config.
    $menus = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      if ($group_content->getGroup()->hasPermission("edit $plugin_id entity", $account)) {
        $menus[] = $group_content->getEntity()->id();
      }
    }

    return $menus;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfig($config_name) {
    if (!isset($this->configurations[$config_name])) {
      $this->configurations[$config_name] = $this->baseStorage->read($config_name);
    }
    return $this->configurations[$config_name];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'GroupMenuConfigOverrides';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
