<?php

namespace Drupal\groupmenu;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\Controller\GroupContentListBuilder;
use Drupal\group\Entity\GroupContentType;

/**
 * Provides a list controller for menu's entities in a group.
 */
class GroupMenuContentListBuilder extends GroupContentListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery();
    $query->sort($this->entityType->getKey('id'));

    // Only show group content for the group on the route.
    $query->condition('gid', $this->group->id());

    // Filter by group menu plugins.
    $plugin_id = 'group_menu:menu';
    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);
    if (!empty($group_content_types)) {
      $query->condition('type', array_keys($group_content_types), 'IN');
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $row = parent::buildHeader();
    unset($row['entity_type'], $row['plugin']);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);
    unset($row['entity_type'], $row['plugin']);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t("There are no menu's related to this group yet.");
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\group\Entity\GroupContentInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    // Improve the edit and delete operation labels.
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Edit relation');
    }
    if (isset($operations['delete'])) {
      $operations['delete']['title'] = $this->t('Delete relation');
    }

    // Slap on redirect destinations for the administrative operations.
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    // Add an operation to view the actual entity.
    if ($entity->getEntity()->access('update')) {
      $operations['view-entity'] = [
        'title' => $this->t('View related entity'),
        'weight' => 101,
        'url' => $entity->getEntity()->toUrl(),
      ];
      $operations['edit-entity'] = [
        'title' => $this->t('Edit related entity'),
        'weight' => 102,
        'url' => $entity->getEntity()->toUrl(),
      ];
    }
    if ($entity->getEntity()->access('delete')) {
      $operations['delete-entity'] = [
        'title' => $this->t('Delete related entity'),
        'weight' => 103,
        'url' => $entity->getEntity()->toUrl('delete-form'),
      ];
    }

    return $operations;
  }

}
