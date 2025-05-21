<?php

namespace Drupal\analytics_batch_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator;

/**
 * Controller for analytics node batch generation.
 */
class AnalyticsBatchController extends ControllerBase {

  /**
   * The analytics node generator service.
   *
   * @var \Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator
   */
  protected $nodeGenerator;

  /**
   * Constructs a new AnalyticsBatchController object.
   *
   * @param \Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator $node_generator
   *   The analytics node generator service.
   */
  public function __construct(AnalyticsNodeGenerator $node_generator) {
    $this->nodeGenerator = $node_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('analytics_batch_generator.generator')
    );
  }

  /**
   * Displays the batch generation form.
   *
   * @return array
   *   A render array.
   */
  public function generatorForm() {
    $build = [
      '#theme' => 'analytics_batch_generator_form',
      '#title' => $this->t('Generate Analytics Nodes'),
      '#description' => $this->t('This will generate analytics nodes for each day from today back to 3 years ago.'),
      '#button_url' => Url::fromRoute('analytics_batch_generator.start_batch'),
      '#button_text' => $this->t('Start Batch Generation'),
    ];

    return $build;
  }

  /**
   * Starts the batch process for node generation.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function startBatch() {
    // Start the batch process.
    $this->nodeGenerator->generateNodes();

    // The batch_process() in the generator will handle the redirect if not in
    // CLI.
    return $this->redirect('analytics_batch_generator.generator_form');
  }

}
