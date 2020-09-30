<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\ListSourceInterface;

/**
 * Interface for list pages widget.
 */
interface ListPagesWidgetInterface {

  /**
   * Builds the widget selection for default values.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListSourceInterface|null $list_source
   *   The list source.
   * @param array $parents
   *   The list of parents.
   *
   * @return mixed
   *   The rendered widget.
   */
  public function buildDefaultValuesWidget(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $parents = []): ?array;

  /**
   * Renders the label for the filter values set as default values.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListSourceInterface|null $list_source
   *   The list source.
   * @param array $filter_value
   *   The filter value.
   *
   * @return string
   *   The label.
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $filter_value = []): string;

  /**
   * Prepares the values to be passed to the URL generator from the submission.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The active filters to be handled by the URL generator.
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array;

  /**
   * Get the active filters for the facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param string $key
   *   The key.
   *
   * @return string|null
   *   The value from active filters.
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key):  ?string;

}
