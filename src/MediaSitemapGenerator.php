<?php

/**
 * @file
 * Contains \Drupal\media_sitemap\MediaSitemapGenerator.
 */

namespace Drupal\media_sitemap;

use Drupal\Core\Url;

/**
 * Class MediaSitemapGenerator.
 *
 * @package Drupal\media_sitemap
 */
class MediaSitemapGenerator implements MediaSitemapGeneratorInterface {

  /**
   * @param \Drupal\Core\Database\Driver\mysql\Connection $database
   */
  public function generateSitemap() {
    $query = \Drupal::database()->select('file_usage', 'fu');
    $query->fields('fu', array('id'));
    $query->join('node', 'n', 'n.nid = fu.id');
    $nids = $query->distinct()->execute()->fetchAll();
    $output = '';
    $total_urls = 0;
    if (count($nids) > 0) {
      $output = '<?xml version="1.0" encoding="UTF-8"?>';
      $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
      foreach ($nids as $nid) {
        // fetch list of media files for each nid.
        $query = \Drupal::database()->select('file_managed', 'fm');
        $query->fields('fm', array('fid', 'filename', 'uri'));
        $query->join('file_usage', 'fu', 'fu.fid = fm.fid');
        $query->condition('fu.id', $nid->id);
        $query->condition('fm.filemime', 'image/%', 'LIKE');
        $files = $query->execute()->fetchAll();

        if (count($files) > 0) {
          $output .= '<url><loc>' . Url::fromRoute('entity.node.canonical', array('node' => $nid->id), array('absolute' => TRUE))
              ->toString() . '</loc>';
          foreach ($files as $file) {
            $output .= '<image:image><image:loc>' . file_create_url($file->uri) . '</image:loc><image:title>' . $file->filename . '</image:title></image:image>';
          }
          $output .= '</url>';
          $total_urls++;
        }
      }
      $output .= '</urlset>';
      // File build path.
      $path = file_create_url(\Drupal::service('file_system')->realpath(file_default_scheme() . "://media_sitemap"));
      if (!is_dir($path)) {
        \Drupal::service('file_system')->mkdir($path);
      }
    }

    $time = time();
    $filename = 'image_sitemap.xml';
    if ($file = file_unmanaged_save_data($output, $path . '/' . $filename, FILE_EXISTS_REPLACE)) {
      \Drupal::configFactory()->getEditable('media_sitemap.settings')->set('image_sitemap_created', $time)->save();
      \Drupal::configFactory()->getEditable('media_sitemap.settings')->set('image_sitemap_number_of_urls', $total_urls)->save();
    }
  }

  public function sitemapGenerateFinished($success, $results, $operations) {
    if ($success) {
      drupal_set_message(t('Image Sitemap Generated Successfully.'));
    }
    else {
      $error_operation = reset($operations);
      drupal_set_message(
        t('An error occurred while processing @operation with arguments : @args',
          array(
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE)))
      );
    }
  }
}
