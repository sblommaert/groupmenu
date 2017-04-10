<?php

namespace Drupal\groupmenu;

use Drupal\group\Entity\GroupContentType;
use \Drupal\menu_ui\MenuListBuilder;

/**
 * Override the default menu overview to exclude group menu's.
 */
class GroupMenuListBuilder extends MenuListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getEntityIds() {
    $plugin_id = 'group_menu:menu';
    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);
    if (empty($group_content_types)) {
      return [];
    }

    // Load all the group menu content to exclude.
    $group_contents = \Drupal::entityTypeManager()
      ->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
      ]);
    $menus = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $menu_name = $group_content->getEntity()->id();
      if (!in_array($menu_name, $menus)) {
        $menus[] = $menu_name;
      }
    }

    // Load all menu's not used as group content.
    $query = $this->getStorage()->getQuery()
      ->condition($this->entityType->getKey('id'), $menus, 'NOT IN')
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

}
