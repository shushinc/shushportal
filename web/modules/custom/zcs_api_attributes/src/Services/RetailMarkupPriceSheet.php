<?php

namespace Drupal\zcs_api_attributes\Services;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provides RetailMarkupPercentage Calculation.
 */
class RetailMarkupPriceSheet  {

  protected $database;


   /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;


  public function __construct(Connection $database) {
    $this->database = $database;
  }


  
    public function RetailMarkupPrice() {
      $price_sheet = $this->PreparePricingCsv();
      $file_system = \Drupal::service('file_system');
      // Get module path (absolute)
      $module_path = DRUPAL_ROOT . '/' . \Drupal::service('extension.list.module')->getPath('zcs_api_attributes');
      // Ensure /files folder exists
      $target_dir = $module_path . '/files';
      $file_system->prepareDirectory($target_dir, FileSystemInterface::CREATE_DIRECTORY);
      // Full file path
      $filename = 'pricing_ratesheet.csv';
      // $filename = 'api_pricing_25th.csv';
      $file_path = $target_dir . '/' . $filename;

      // Delete old file if exists
      if (file_exists($file_path)) {
        unlink($file_path);
      }

     // Save new content
     file_put_contents($file_path, $price_sheet['filecontent']);
      try {
        \Drupal::logger('retail_markup_api_hit')->info("API_HIT");
        $client = \Drupal::httpClient();
        $response = $client->request('POST', 'https://136.115.229.241:32443/upload_pricing', [
          'verify' => false, // -k in curl
          'multipart' => [
            [
              'name' => 'file',
              'contents' => fopen($file_path, 'r'),
              'filename' => $price_sheet['filename'],
            ],
          ],
        ]);
        if($response->getStatusCode() == '200') {
           $res =  $response->getBody()->getContents();
           \Drupal::logger('retail_markup')->error($res);
           return $res;
        } 
        else {
          \Drupal::logger('retail_markup')->error("Error is posting the data");
          return FALSE;
        }      
      }
      catch (\Exception $e) {
        \Drupal::logger('retail_markup')->error($e->getMessage());
        return FALSE;
      }
    }



  public function PreparePricingCsv() {
    $formatted_array = [
      'available' => 'TRUE',
      'enabled' => 'TRUE',
      'api_attribute' => 'SAMPLE',
      'endpoint'=> 'SAMPLE',
      'international_price' => 'internaltion_price',
      'domestic_price' => 'sample_domestic_price',
      'effective_date' => 'effective_price',
      'currency' => 'currency',
      'markup_percentage'=> 'markup_perecentage',
      'discount_percentage' => 'discount_percentage',
    ];
    $header = [
      'Available',
      'Enabled',
      'APIAttributes',
      'Endpoint',
      'InternationalPrice',
      'DomesticPrice',
      'EffectiveDate',
      'Currency',
      'MarkupPercentage',
      'DiscountPercentage',
    ];
    $attachments = [
      'filecontent' => $this->array2csv([$header, $formatted_array]),
      'filename' => 'price_sheet.csv',
      'filemime' => 'application/csv',
    ];
    return $attachments;
  }


    /**
   * Helper function for the file attachment.
   */
  public function array2csv($data, $delimiter = ',', $enclosure = '"', $escape_char = "\\") {
    $f = fopen('php://memory', 'r+');
    foreach ($data as $item) {
      fputcsv($f, $item, $delimiter, $enclosure, $escape_char);
    }
    rewind($f);
    return stream_get_contents($f);
  }

}