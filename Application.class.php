<?
namespace ClrHome;

define('FLASH_APP_PAGE_START', 0x4000);

include_once(__DIR__ . '/TiObject.class.php');

/**
 * An enum representing the calculator series to target during export.
 */
abstract class FlashSeries extends Enum {
  const TI73 = 0x74;
  const TI83P = 0x73;
  const TI89 = 0x98;
  const TI92P = 0x88;
}

abstract class HexLineType extends Enum {
  const DATA = 0x00;
  const END = 0x01;
  const PAGE = 0x02;
}

/**
 * An application.
 */
class Application extends TiObject implements \ArrayAccess {
  private $hexLineLength = 0x20;
  private $pages = array();
  private $revision = '1.0';
  private $series = FlashSeries::TI83P;
  private $timestamp = 0;

  public function __construct() {
    $this->timestamp = time();
  }

  /**
   * Returns an application constructed from a string.
   * @param string $packed A string in TI flash file format.
   */
  final public static function fromString($packed) {
    throw new \RuntimeException();
  }

  private static function dataToHex($address, $type, $data) {
    $line_packed = pack('CnCa*', strlen($data), $address, $type, $data);

    return ':' . strtoupper(
      bin2hex($line_packed . chr(-array_sum(unpack('C*', $line_packed))))
    ) . "\r\n";
  }

  /**
   * Returns the application name as a character string.
   */
  final public function getName() {
    return isset($this->name) ? $this->name : null;
  }

  /**
   * Sets the application name as a character string.
   * @param string $name The application name as a character string.
   */
  final public function setName($name) {
    $this->name = substr($name, 0, 8);
  }

  final public function getType() {
    return VariableType::APPLICATION;
  }

  /**
   * Returns the application in TI flash file format as a string.
   * @param string $comment An optional comment to include in the file.
   */
  final public function toString($comment = '') {
    $contents_hex = '';

    foreach ($this->pages as $page_number => $page) {
      $contents_hex .=
          self::dataToHex(0x0000, HexLineType::PAGE, pack('n', $page_number));
      $page_length = strlen($page);

      for (
        $line_start = 0;
        $line_start < $page_length;
        $line_start += $this->hexLineLength
      ) {
        $contents_hex .= self::dataToHex(
          FLASH_APP_PAGE_START + $line_start,
          HexLineType::DATA,
          substr($page, $line_start, $this->hexLineLength)
        );
      }
    }

    $contents_hex .= self::dataToHex(0x0000, HexLineType::END, '');

    return pack(
      'a8C6nCa8x23C2x24Va*',
      '**TIFL**',
      $this->revision[0],
      $this->revision[2],
      0x01, 0x88,
      hexdec(idate('m', $this->timestamp)),
      hexdec(idate('d', $this->timestamp)),
      hexdec(idate('Y', $this->timestamp)),
      strlen($this->name),
      $this->name,
      $this->series,
      VariableType::APPLICATION,
      strlen($contents_hex),
      $contents_hex
    );
  }

  public function offsetExists($page_number) {
    self::validateIndex($page_number);
    return array_key_exists($page_number, $pages);
  }

  public function offsetGet($page_number) {
    self::validateIndex($page_number);
    return $this->pages[$page_number];
  }

  public function offsetSet($page_number, $page) {
    self::validateIndex($page_number);
    $this->pages[$page_number] = $page;
  }

  public function offsetUnset($page_number) {
    self::validateIndex($page_number);
    unset($this->pages[$page_number]);
  }

  private static function validateIndex($page_number) {
    if ($page_number < 0 || $page_number !== (int)$page_number) {
      throw new \OutOfRangeException(
        "Page number $page_number must be a nonnegative integer"
      );
    }
  }

  /**
   * Returns the number of bytes to store per line of Intel hex output.
   */
  final public function getHexLineLength() {
    return $this->line_length;
  }

  /**
   * Sets the number of bytes to store per line of Intel hex output.
   * @param number $line_length The number of bytes to store per line.
   */
  final public function setHexLineLength($line_length) {
    if ($line_length < 1 || $line_length !== (int)$line_length) {
      throw new \OutOfRangeException(
        "Line length $line_length must be a positive integer"
      );
    }

    $this->hexLineLength = (int)$line_length;
  }

  /**
   * Returns the revision number of this application as a float.
   */
  final public function getRevision() {
    return (float)$this->revision;
  }

  /**
   * Sets the revision number of this application.
   * @param number $revision The revision number to set as a float.
   */
  final public function setRevision($revision) {
    $revision = number_format($revision, 1);

    if (!preg_match('/^\d.\d$/', $revision)) {
      throw new \OutOfRangeException(
        "Revision number $revision must be between 0.0 and 9.9"
      );
    }

    $this->revision = $revision;
  }

  /**
   * Returns the calculator series this variable should target during export.
   */
  final public function getSeries() {
    return $this->series;
  }

  /**
   * Sets the calculator series this variable should target during export.
   * @param FlashSeries $series The calculator series to target.
   */
  final public function setSeries($series) {
    $this->series = FlashSeries::validate($series);
  }

  /**
   * Returns the timestamp of this application in ISO 8601 date format.
   */
  final public function getTimestamp() {
    return date($this->timestamp, 'Y-m-d');
  }

  /**
   * Sets the timestamp of this application.
   * @param string $timestamp The timestamp to set as a parsable string.
   */
  final public function setTimestamp($timestamp) {
    $timestamp = strtotime($timestamp);

    if ($timestamp === false) {
      throw new \InvalidArgumentException(
        "Unable to parse $timestamp as a timestamp"
      );
    }

    $this->timestamp = $timestamp;
  }
}
?>
