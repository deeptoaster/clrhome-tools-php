<?
namespace ClrHome;

// TODO: move from conf.xml to catalog.xml
define('TOKENIZER_CATALOG_URL', 'https://clrhome.org/catalog/conf.xml');

abstract class Enum {
  public static function getConstList() {
    static $const_list = null;

    if ($const_list === null) {
      $const_list = array();
      $reflection = new \ReflectionClass(static::class);

      while ($reflection !== false) {
        $const_list = array_merge($reflection->getConstants(), $const_list);
        $reflection = $reflection->getParentClass();
      }
    }

    return $const_list;
  }

  public static function validate($value) {
    if (!in_array($value, static::getConstList())) {
      throw new \InvalidArgumentException(
        sprintf('Invalid value for %s: %s', static::class, $value)
      );
    }

    return $value;
  }
}

abstract class Series extends Enum {
  const TI83 = '**TI83**';
  const TI83P = '**TI83F*';
}

class Tokenizer {
  protected $catalog;
  protected $series = Series::TI83P;

  public function __construct($catalog_file = __DIR__ . '/catalog.xml') {
    if (!file_exists($catalog_file)) {
      $catalog_handle = fopen(TOKENIZER_CATALOG_URL, 'r');

      if ($catalog_handle === false) {
        throw new UnexpectedValueException(
          'Unable to download catalog file at ' . $catalog_handle
        );
      }

      if (file_put_contents($catalog_file, $catalog_handle) === false) {
        throw new UnexpectedValueException(
          "Unable to write catalog file at $catalog_file"
        );
      }
    }

    $this->catalog = simplexml_load_file($catalog_file);

    if ($this->catalog === false) {
      throw new UnexpectedValueException(
        "Unable to read catalog file at $catalog_file"
      );
    }
  }

  public function getSeries() {
    return $this->series;
  }

  public function setSeries($series) {
    $this->series = Series::validate($series);
  }
}
?>
