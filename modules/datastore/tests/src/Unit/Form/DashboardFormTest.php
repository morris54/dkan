<?php

namespace Drupal\Tests\datastore\Unit\Form;

use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Form\FormState;
use Drupal\common\DatasetInfo;
use Drupal\Core\Pager\Pager;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\datastore\Form\DashboardForm;
use Drupal\harvest\Service as Harvest;
use Drupal\metastore\Service as MetastoreService;
use Drupal\Tests\metastore\Unit\ServiceTest;
use MockChain\Chain;
use MockChain\Options;
use PHPUnit\Framework\TestCase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardFormTest extends TestCase {

  /**
   * The ValidMetadataFactory class used for testing.
   *
   * @var \Drupal\metastore\ValidMetadataFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $validMetadataFactory;

  public function setUp(): void {
    parent::setUp();
    $this->validMetadataFactory = ServiceTest::getValidMetadataFactory($this);
  }

  /**
   * Test that the correct form filter fields are added.
   */
  public function testBuildFilters(): void {
    $container = $this->buildContainerChain()
      ->add(RequestStack::class, 'getCurrentRequest', new Request(['uuid' => 'test']))
      ->getMock();
    \Drupal::setContainer($container);

    $form = DashboardForm::create($container);
    $form = $form->buildForm([], new FormState());

    // Assert
    $this->assertEquals('textfield', $form['filters']['uuid']['#type']);
    $this->assertEquals('select', $form['filters']['harvest_id']['#type']);
    $this->assertEquals('actions', $form['filters']['actions']['#type']);
    $this->assertEquals('submit', $form['filters']['actions']['submit']['#type']);
  }

  /**
   * Test that the correct form table elements are added.
   */
  public function testBuildTable(): void {
    $container = $this->buildContainerChain()
      ->add(RequestStack::class, 'getCurrentRequest', new Request(['uuid' => 'test']))
      ->getMock();
    \Drupal::setContainer($container);

    $form = DashboardForm::create($container)->buildForm([], new FormState());

    // Assert
    $this->assertEquals('table', $form['table']['#theme']);
    $this->assertEquals('pager', $form['pager']['#type']);
  }

  /**
   * Test that a table built with no datasets has no rows.
   */
  public function testBuildTableRowsWithNoDatasets(): void {
    $container = $this->buildContainerChain()->getMock();
    \Drupal::setContainer($container);

    $form = DashboardForm::create($container)->buildForm([], new FormState());

    $this->assertEmpty($form['table']['#rows']);
  }

  /**
   * Test building the dashboard table with a harvest ID filter.
   */
  public function testBuildTableRowsWithHarvestIdFilter() {
    $info = [
      'uuid' => 'dataset-1',
      'title' => 'Dataset 1',
      'revision_id' => '2',
      'moderation_state' => 'published',
      'modified_date_metadata' => '2020-01-15',
      'modified_date_dkan' => '2021-02-11',
    ];
    $distribution = [
      'distribution_uuid' => 'dist-1',
      'fetcher_status' => 'done',
      'fetcher_percent_done' => 100,
      'importer_status' => 'done',
      'importer_percent_done' => 100,
    ];

    $container = $this->buildContainerChain()
      ->add(RequestStack::class, 'getCurrentRequest', new Request(['harvest_id' => 'dataset-1']))
      ->add(DatasetInfo::class, 'gather', ['latest_revision' => $info + ['distributions' => [$distribution]]])
      ->getMock();
    \Drupal::setContainer($container);
    $form = DashboardForm::create($container)->buildForm([], new FormState());

    $this->assertEquals(1, count($form['table']['#rows']));
    $this->assertEquals('dataset-1', $form['table']['#rows'][0][0]['data']);
    $this->assertEquals('Dataset 1', $form['table']['#rows'][0][1]);
    $this->assertEquals('NEW', $form['table']['#rows'][0][4]['data']);
  }

  /**
   * Test building the dashboard table with a UUID filter.
   */
  public function testBuildTableRowsWithUuidFilter() {
    $info = [
      'uuid' => 'test',
      'title' => 'Title',
      'revision_id' => '2',
      'moderation_state' => 'published',
      'modified_date_metadata' => '2020-01-15',
      'modified_date_dkan' => '2021-02-11',
    ];
    $distribution = [
      'distribution_uuid' => 'dist-1',
      'fetcher_status' => 'done',
      'fetcher_percent_done' => 100,
      'importer_status' => 'done',
      'importer_percent_done' => 100,
    ];

    $container = $this->buildContainerChain()
      ->add(RequestStack::class, 'getCurrentRequest', new Request(['uuid' => 'test']))
      ->add(DatasetInfo::class, 'gather', ['latest_revision' => $info + ['distributions' => [$distribution]]])
      ->getMock();
    \Drupal::setContainer($container);
    $form = DashboardForm::create($container)->buildForm([], new FormState());

    $this->assertEquals(1, count($form['table']['#rows']));
    $this->assertEquals('test', $form['table']['#rows'][0][0]['data']);
    $this->assertEquals('Title', $form['table']['#rows'][0][1]);
    $this->assertEquals('N/A', $form['table']['#rows'][0][4]['data']);
  }

  /**
   * Test building the dashboard table without a filter.
   */
  public function testBuildTableRowsWithAllDatasets() {
    $datasetInfo = [
      'latest_revision' => [
        'uuid' => 'dataset-1',
        'revision_id' => '1',
        'moderation_state' => 'published',
        'title' => 'Dataset 1',
        'modified_date_metadata' => '2019-08-12',
        'modified_date_dkan' => '2021-07-08',
        'distributions' => [
          [
            'distribution_uuid' => 'dist-1',
            'fetcher_status' => 'waiting',
            'fetcher_percent_done' => 0,
            'importer_status' => 'waiting',
            'importer_percent_done' => 0,
          ],
        ],
      ],
    ];

    $nonHarvestDatasetInfo = [
      'latest_revision' => [
        'uuid' => 'non-harvest-dataset',
        'revision_id' => '1',
        'moderation_state' => 'published',
        'title' => 'Non-Harvest Dataset',
        'modified_date_metadata' => '2019-08-12',
        'modified_date_dkan' => '2021-07-08',
        'distributions' => [
          [
            'distribution_uuid' => 'dist-2',
            'fetcher_status' => 'done',
            'fetcher_percent_done' => 100,
            'importer_status' => 'done',
            'importer_percent_done' => 100,
          ],
        ],
      ],
    ];

    $datasetInfoOptions = (new Options())
      ->add('dataset-1', $datasetInfo)
      ->add('non-harvest-dataset', $nonHarvestDatasetInfo);

    $container = $this->buildContainerChain()
      ->add(MetastoreService::class, 'count', 2)
      ->add(MetastoreService::class, 'getRangeUuids', [$datasetInfo['latest_revision']['uuid'], $nonHarvestDatasetInfo['latest_revision']['uuid']])
      ->add(DatasetInfo::class, 'gather', $datasetInfoOptions);

    \Drupal::setContainer($container->getMock());
    $form = DashboardForm::create($container->getMock())->buildForm([], new FormState());

    // Assert that there are both datasets: harvested and non-harvested.
    $this->assertEquals(2, count($form['table']['#rows']));

    $this->assertEquals('dataset-1', $form['table']['#rows'][0][0]['data']);
    $this->assertEquals('Dataset 1', $form['table']['#rows'][0][1]);
    $this->assertEquals('NEW', $form['table']['#rows'][0][4]['data']);

    $this->assertEquals('non-harvest-dataset', $form['table']['#rows'][1][0]['data']);
    $this->assertEquals('Non-Harvest Dataset', $form['table']['#rows'][1][1]);
    $this->assertEquals('N/A', $form['table']['#rows'][1][4]['data']);
  }

  /**
   * Test building the dashboard table for a dataset without a distribution.
   */
  public function testBuildTableRowsDatasetWithNoDistribution() {
    $datasetInfo = [
      'latest_revision' => [
        'uuid' => 'dataset-1',
        'revision_id' => '1',
        'moderation_state' => 'published',
        'title' => 'Dataset 1',
        'modified_date_metadata' => '2019-08-12',
        'modified_date_dkan' => '2021-07-08',
        'distributions' => ['Not found'],
      ],
    ];

    $container = $this->buildContainerChain()
      ->add(MetastoreService::class, 'count', 1)
      ->add(MetastoreService::class, 'getRangeUuids', [$datasetInfo['latest_revision']['uuid']])
      ->add(DatasetInfo::class, 'gather', $datasetInfo)
      ->getMock();
    \Drupal::setContainer($container);

    $form = DashboardForm::create($container)->buildForm([], new FormState());
    $this->assertEmpty($form['table']['#rows'][0][7]['data']['#rows']);
  }

  /**
   * Build container mock chain object.
   */
  private function buildContainerChain(): Chain {
    $options = (new Options())
      ->add('dkan.harvest.service', Harvest::class)
      ->add('dkan.common.dataset_info', DatasetInfo::class)
      ->add('dkan.metastore.service', MetastoreService::class)
      ->add('pager.manager', PagerManagerInterface::class)
      ->add('request_stack', RequestStack::class)
      ->add('string_translation', TranslationManager::class)
      ->index(0);

    $runInfo = (new Options())
      ->add(['dataset-1', 'test'], json_encode([
        'status' => [
          'extract' => 'SUCCESS',
          'load' => [
            'dataset-1' => 'NEW'
          ]
        ]
      ]))
      ->add(['test', 'test'], json_encode([]));

    return (new Chain($this))
      ->add(Container::class, 'get', $options)
      ->add(DatasetInfo::class, 'gather', ['notice' => 'Not found'])
      ->add(Harvest::class, 'getAllHarvestIds', ['test', 'dataset-1'])
      ->add(Harvest::class,'getAllHarvestRunInfo', ['test'])
      ->add(Harvest::class,'getHarvestRunInfo', $runInfo)
      ->add(MetastoreService::class, 'count', 0)
      ->add(MetastoreService::class, 'getRangeUuids', [])
      ->add(PagerManagerInterface::class,'createPager', Pager::class)
      ->add(Pager::class,'getCurrentPage', 0);
  }
}
