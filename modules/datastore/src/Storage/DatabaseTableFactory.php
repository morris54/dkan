<?php

namespace Drupal\datastore\Storage;

use Contracts\FactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\indexer\IndexManager;

/**
 * DatabaseTable data object factory.
 */
class DatabaseTableFactory implements FactoryInterface {

  /**
   * Drupal database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $connection;

  /**
   * Optional index manager service.
   *
   * @var null|\Drupal\indexer\IndexManager
   */
  private $indexManager;

  /**
   * Database table data objects.
   *
   * @var \Drupal\datastore\Storage\DatabaseTable
   */
  private $databaseTables = [];

  /**
   * Constructor.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Set an optional index manager service.
   *
   * @param \Drupal\indexer\IndexManager $indexManager
   *   Index manager.
   */
  public function setIndexManager(IndexManager $indexManager) {
    $this->indexManager = $indexManager;
  }

  /**
   * Inherited.
   *
   * @inheritdoc
   */
  public function getInstance(string $identifier, array $config = []) {
    if (!isset($config['resource'])) {
      throw new \Exception("config['resource'] is required");
    }

    $resource = $config['resource'];

    if (!isset($this->databaseTables[$identifier])) {
      $this->databaseTables[$identifier] = $this->getDatabaseTable($resource);
      if ($this->indexManager) {
        $this->databaseTables[$identifier]->setIndexManager($this->indexManager);
      }
    }

    return $this->databaseTables[$identifier];
  }

  /**
   * Protected.
   */
  protected function getDatabaseTable($resource) {
    $databaseTable = new DatabaseTable($this->connection, $resource);
    return $databaseTable;
  }

}
