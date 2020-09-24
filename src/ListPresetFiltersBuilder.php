<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\Exception\InvalidProcessorException;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesWidgetInterface;

/**
 * Builder service for preset filters.
 */
class ListPresetFiltersBuilder {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * ListPresetFiltersBuilder constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facets manager.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetsManager = $facetManager;
  }

  /**
   * Ajax request handler for editing default values for filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function editDefaultValue(array &$form, FormStateInterface $form_state): array {
    $key = $form_state->getValue('oe_list_pages_form_key');
    $form[$key]['preset_filters_wrapper']['#open'] = TRUE;
    return $form[$key];
  }

  /**
   * Ajax request handler for updating default value for a filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function setDefaultValues(array &$form, FormStateInterface $form_state): array {
    $key = $form_state->getValue('oe_list_pages_form_key');
    $form[$key]['preset_filters_wrapper']['#open'] = TRUE;
    return $form[$key];
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function buildDefaultFilters(array $form, FormStateInterface $form_state, string $form_key, ListSourceInterface $list_source = NULL, array $available_filters = [], array $preset_filters = []) {

    // List source doesn't exist yet.
    if (empty($list_source)) {
      return $form;
    }

    // Store the form key for ajax processing.
    $form['oe_list_pages_form_key'] = [
      '#type' => 'value',
      '#value' => $form_key,
    ];

    $form[$form_key]['preset_filters_wrapper'] = [
      '#type' => 'container',
      '#title' => t('Default filter values'),
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'list-page-default-filters',
      ],
    ];

    $form[$form_key]['preset_filters_wrapper']['label'] = [
      '#title' => t('Default filter values'),
      '#type' => 'label',
    ];

    $current_filters = $form_state->getValue('preset_filters_wrapper')['current_filters'] ?? $preset_filters;
    $triggering_element = $form_state->getTriggeringElement();

    // Adding default filter value.
    if (!empty($triggering_element) && $triggering_element['#name'] == 'set-default-filter') {
      $filter_key = $form_state->getValue('preset_filters_wrapper')['edit']['filter_key'];
      // Replace correct labels.
      $facet = $this->getFacetById($list_source, $filter_key);
      if (!empty($facet)) {
        $widget = $facet->getWidgetInstance();
        if ($widget instanceof ListPagesWidgetInterface) {
          // Get active filters from form_state values in the filter key.
          $active_filters[$facet->id()] = $widget->prepareValueForUrl($facet, $form, $form_state);
        }
      }

      $filter_value = $active_filters[$filter_key] ?? '';
      if (!empty($filter_key)) {
        $current_filters = array_merge($current_filters, [$filter_key => $filter_value]);
      }
    }
    // Removing default filter value.
    elseif (!empty($triggering_element) && $triggering_element['#name'] == 'remove-default-filter') {
      $filter_key = $form_state->getValue('preset_filters_wrapper')['edit']['filter_key'];
      unset($current_filters[$filter_key]);
    }

    $form[$form_key]['preset_filters_wrapper']['current_filters'] = [
      '#type' => 'value',
      '#value' => $current_filters,
    ];

    $filter_key = $form_state->getValue('preset_filters_wrapper')['summary']['add_new'];
    if (empty($filter_key)) {
      $form = $this->buildSummaryPresetFilters($form, $form_state, $form_key, $list_source, $available_filters, $current_filters);
    }
    else {
      $form = $this->buildEditPresetFilter($form, $form_state, $form_key, $list_source, $available_filters, $current_filters, $filter_key);
    }

    return $form;
  }

  /**
   * Builds the summary for the default filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_key
   *   The form key.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $available_filters
   *   An array of available filters.
   * @param array $current_filters
   *   An array of currently set filters.
   *
   * @return array
   *   The built form.
   */
  protected function buildSummaryPresetFilters(array $form, FormStateInterface $form_state, string $form_key, ListSourceInterface $list_source, array $available_filters, array $current_filters) {
    $header = [
      ['data' => t('Filter')],
      ['data' => t('Default value')],
    ];

    $rows = [];

    foreach ($current_filters as $filter_key => $filter_value) {
      $facet = $this->getFacetById($list_source, $filter_key);
      $active_items = $facet->getActiveItems();
      $facet->setActiveItems($filter_value);
      foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_BUILD) as $processor) {
        if (!$processor instanceof BuildProcessorInterface) {
          throw new InvalidProcessorException("The processor {$processor->getPluginDefinition()['id']} has a build definition but doesn't implement the required BuildProcessorInterface interface");
        }
        $results = $processor->build($facet, []);
      }
      foreach ($results as $result) {
        if ($result->getRawValue() == $filter_value[0]) {
          $filter_value = $result->getDisplayValue();
        }
      }
      // Reset active items.
      $facet->setActiveItems($active_items);

      $filter_value_label = is_array($filter_value) ? implode(', ', $filter_value) : $filter_value;
      $rows[] = [$available_filters[$filter_key], $filter_value_label];
    }

    $form[$form_key]['preset_filters_wrapper']['summary'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Summary'),
    ];

    $form[$form_key]['preset_filters_wrapper']['summary']['table'] = [
      '#type' => 'table',
      '#title' => t('Default filter values'),
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No default values set.'),
    ];

    $form[$form_key]['preset_filters_wrapper']['summary']['add_new'] = [
      '#type' => 'select',
      '#title' => $this->t('Set default value for:'),
      '#options' => ['' => $this->t('- None -')] + $available_filters,
      '#ajax' => [
        'callback' => [$this, 'editDefaultValue'],
        'wrapper' => $form[$form_key]['#id'],
      ],
    ];

    return $form;
  }

  /**
   * Builds the edit filter section.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_key
   *   The form key.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $available_filters
   *   An array of available filters.
   * @param array $current_filters
   *   An array of currently set filters.
   * @param string $filter_key
   *   The filter key.
   *
   * @return array
   *   The built form.
   */
  protected function buildEditPresetFilter(array $form, FormStateInterface $form_state, string $form_key, ListSourceInterface $list_source, array $available_filters, array $current_filters, string $filter_key) {
    $form[$form_key]['preset_filters_wrapper']['edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Set default value for :filter', [':filter' => $available_filters[$filter_key]]),
    ];

    // Execute list source query so we have results.
    $list_source->getQuery()->execute();

    $facet = $this->getFacetById($list_source, $filter_key);
    if (!empty($facet)) {
      // Set active item for value edition.
      if (!empty($current_filters[$filter_key])) {
        $facet->setActiveItems($current_filters[$filter_key]);
      }
      $this->facetsManager->build($facet);
      $form[$form_key]['preset_filters_wrapper']['edit'][$facet->id()] = $facet->getWidgetInstance()
        ->build($facet);
      $form[$form_key]['preset_filters_wrapper']['edit'][$facet->id()]['#tree'] = FALSE;
      $form[$form_key]['preset_filters_wrapper']['edit'][$facet->id()]['#parents'] = ['preset_filters_wrapper', 'edit'][$facet->id()];
    }

    $form[$form_key]['preset_filters_wrapper']['edit']['filter_key'] = [
      '#value' => $filter_key,
      '#type' => 'value',
    ];

    $ajax_definition = [
      'callback' => [$this, 'setDefaultValues'],
      'wrapper' => $form[$form_key]['#id'],
    ];

    $form[$form_key]['preset_filters_wrapper']['edit']['set_value'] = [
      '#value' => $this->t('Set default value'),
      '#type' => 'button',
      '#name' => 'set-default-filter',
      '#ajax' => $ajax_definition,
    ];

    $form[$form_key]['preset_filters_wrapper']['edit']['cancel'] = [
      '#value' => $this->t('Remove default value'),
      '#type' => 'button',
      '#name' => 'remove-default-filter',
      '#ajax' => $ajax_definition,
    ];

    return $form;
  }

  /**
   * Get a facet by id.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $listSource
   *   The list source.
   * @param string $id
   *   The facet id.
   *
   * @return \Drupal\facets\FacetInterface|null
   *   The facet if found.
   */
  public function getFacetById(ListSourceInterface $listSource, string $id): ?FacetInterface {
    $facets = $this->facetsManager->getFacetsByFacetSourceId($listSource->getSearchId());
    foreach ($facets as $facet) {
      if ($id === $facet->id()) {
        return $facet;
      }
    }
  }

}
