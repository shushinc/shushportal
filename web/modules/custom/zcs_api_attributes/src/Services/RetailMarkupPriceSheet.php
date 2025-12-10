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

  
    /**
     * {@inheritdoc}
     */
    public function __construct(Connection $database) {
      $this->database = $database;
    }
    /**
     * {@inheritdoc}
     */
    public function RetailMarkupPrice($pricing_id) {
      $price_sheet = $this->PreparePricingCsv($pricing_id);
      $file_system = \Drupal::service('file_system');
      // Private folder for your module
      $directory = 'private://pricing';

      // Ensure directory exists
      $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      // Filename
      $filename = 'pricing_ratesheet.csv';
      $file_uri = $directory . '/' . $filename;
      if (file_exists($file_system->realpath($file_uri))) {
        $file_system->delete($file_uri);
      }
      $file_system->saveData(
        $price_sheet['filecontent'],
        $file_uri,
        FileSystemInterface::EXISTS_REPLACE
      );
     $endpoint = \Drupal::config('zcs_custom.settings')->get('proposed_api_endpoint');
      try {
        \Drupal::logger('retail_markup_api_hit')->info("API_HIT");
        $client = \Drupal::httpClient();
        $response = $client->request('POST', $endpoint, [
          'verify' => false,
          'multipart' => [
            [
              'name' => 'file',
              'contents' => fopen($file_uri, 'r'),
              'filename' => $price_sheet['filename'],
            ],
          ],
        ]);
        if($response->getStatusCode() == '200') {
           $res =  $response->getBody()->getContents();
           \Drupal::logger('retail_markup')->info($res);
           return TRUE;
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



  /**
   * {@inheritdoc}
   */

  public function PreparePricingCsv($pricing_id) {
    $data = $this->database->select('attributes_page_data', 'apd')
    ->fields('apd', ['approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'currency_locale', 'effective_date', 'attribute_status', 'page_data', 'retail_markup_percentage', 'effective_date_integer'])
    ->condition('id', $pricing_id)
    ->execute()->fetchObject();
    if (!empty($data)) {
      $rates = Json::decode($data->page_data);
      $international = $rates['international'];
      $domestic = $rates['domestic'];
      $final = [];
      $code = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
      $lists_currencies = require __DIR__ . '/../../resources/currencies.php';
      foreach($lists_currencies as $currency_data) {
        if ($currency_data['locale'] === $code) {
          $currency_code = $currency_data['alphabeticCode'];
        }
      }
  

      foreach ($international as $attr_id => $intl_value) {
        $rate_sheet_attribute = $this->getNodeAttributes($attr_id);
        $final[$attr_id] = [
         'available' => 'TRUE',
         'enabled' => 'TRUE',
         'api_attribute' => $rate_sheet_attribute['title'] ?? '',
         'endpoint' => $rate_sheet_attribute['endpoint'] ?? '',
         'international_price'  => $intl_value,
         'domestic_price'       => $domestic[$attr_id] ?? null,
         'effective_date' =>  date('m/d/Y', $data->effective_date_integer),
         'currency' => $currency_code ,
         'markup_percentage'=> $data->retail_markup_percentage,
         'discount_percentage' => $data->retail_markup_percentage
        ];
      }
    }
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
    $rows = [];
    $rows[] = $header; 
    foreach ($final as $row) {
      $rows[] = [
        $row['available'],
        $row['enabled'],
        $row['api_attribute'],
        $row['endpoint'],
        $row['international_price'],
        $row['domestic_price'],
        $row['effective_date'],
        $row['currency'],
        $row['markup_percentage'],
        $row['discount_percentage'],
      ];
    }
    $attachments = [
      'filecontent' => $this->array2csv($rows),
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

  public function getNodeAttributes($node_id) {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $node_storage->load($node_id);
    $data = [];
    if ($node) {
      $data['title'] = $node->getTitle();
      $data['endpoint'] = $node->get('field_endpoint')->value;
    }
    return $data;
  }

}