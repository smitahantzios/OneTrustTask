<?php

namespace Drupal\events_csv_importer\Plugin\Importer;

use Drupal\Component\Utility\Unicode;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\csv_importer\Plugin\ImporterBase;

/**
 * Class EventImporter.
 *
 * @Importer(
 *   id = "event_content_importer",
 *   entity_type = "node",
 *   label = @Translation("Events content importer")
 * )
 */
class EventImporter extends ImporterBase {
   /**
   * {@inheritdoc}
   */
  public function data() {
    $csv = $this->configuration['csv'];
    $return = [];

    if ($csv && is_array($csv)) {
      $csv_fields = $csv[0];
      unset($csv[0]);
      $state = '';
      foreach ($csv as $index => $data) {
        foreach ($data as $key => $content) {
          if (isset($csv_fields[$key])) {
            $content = Unicode::convertToUtf8($content, mb_detect_encoding($content));
            $field = $csv_fields[$key];
            
            // Check if event with the same name exists
            if($field == 'title' && $this->is_event_exists_by_name($content)) {
                break;
            }
            // Replace values with drupal formatted values 
            switch ($field) {
              case 'field_state':
                  $content = $this->get_vocabulary_term_id($content, 'state');
                  $state = $content;
                  break;
              case 'field_city':
                  $content = $this->get_vocabulary_term_id($content, 'city', $state);
                  break;
              case 'field_date':
                  $date = new \DateTime($content);                     
                  $content = DrupalDateTime::createFromDateTime($date)->format('Y-m-d');
                  break;
              default: 
                  break;
            }
            $return['content'][$index][$field] = $content;
          }
        }

        if (isset($return[$index])) {
          $return['content'][$index] = array_intersect_key($return[$index], array_flip($this->configuration['fields']));
        }
      }
    }
    return $return;
  }  

  /*
  Check if taxonomy term exists and create if not found
  @return int
  */
  function get_vocabulary_term_id($term_value, $vocabulary, $state = null) {
    $terms = taxonomy_term_load_multiple_by_name($term_value, $vocabulary);
    if (!empty($terms)) {
      $term = reset($terms);
    }
    else {
      $data = array(
        'name' => $term_value,
        'vid' => $vocabulary,
      );
      if(!empty($state)) {
          $data['field_state'] = $state;
      }
      $term = Term::create($data);
      $term->save();
    }
    return $term->id();
  }
  
  /*
   * Check if event with the same name exists
   * @return boolean
   */
  function is_event_exists_by_name($title) {
      $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
            'type' => 'event',
            'title' => $title
        ]);
      return !empty($nodes);
  }
}
