<?
namespace ClrHome;

define('FLASH_APP_PAGE_START', 0x4000);
define('FLASH_FILE_SIGNATURE', '**TIFL**');

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
    $unpacked = unpack(
      'a8file_signature/C2revision/C2/Cmonth/Cdate/nyear/Cname_length/a8name/x23/Cseries/Ctype/x24/Vcontents_length/a*contents',
      $packed
    );

    if (
      $unpacked === false ||
          $unpacked['file_signature'] !== FLASH_FILE_SIGNATURE
    ) {
      throw new \OutOfBoundsException('Invalid application file format');
    }

    $application = new static();

    $application->setRevision(sprintf(
      '%u.%u',
      self::validateInteger($unpacked['revision1'], 'revision number'),
      self::validateInteger($unpacked['revision2'], 'revision number')
    ));

    $application->setTimestamp(sprintf(
      '%u-%u-%u',
      self::validateInteger(dechex($unpacked['year']), 'timestamp year'),
      self::validateInteger(dechex($unpacked['month']), 'timestamp month'),
      self::validateInteger(dechex($unpacked['date']), 'timestamp date')
    ));

    if ($unpacked['name_length'] < 0 || $unpacked['name_length'] > 8) {
      throw new \OutOfBoundsException(
        "Name length $unpacked[name_length] must be between 0 and 8"
      );
    }

    $application->setName(substr(
      $unpacked['name'],
      0,
      $unpacked['name_length']
    ));

    $application->setSeries($unpacked['series']);

    if ($unpacked['contents_length'] > strlen($unpacked['contents'])) {
      throw new \OutOfBoundsException(
        'Hex contents length exceeds file length'
      );
    }

    $unpacked['contents'] =
        substr($unpacked['contents'], 0, $unpacked['contents_length']);
    $line = strtok($unpacked['contents'], "\r\n");
    $page_number = null;
    $address = null;
    $done = false;

    while ($line !== false) {
      if ($done) {
        throw new OutOfBoundsException('Extra data present after end block');
      }

      $line_unpacked = self::dataFromHex($line);

      switch ($line_unpacked['type']) {
        case HexLineType::DATA:
          if ($page_number === null) {
            throw new OutOfBoundsException(
              'Expected page number to be set before data'
            );
          }

          if ($line_unpacked['address'] < FLASH_APP_PAGE_START) {
            throw new OutOfBoundsException(
              "Address $line_unpacked[address] cannot be lower than $4000"
            );
          }

          $line_start = $line_unpacked['address'] - FLASH_APP_PAGE_START;

          $application->pages[$page_number] = substr_replace(
            str_pad($application->pages[$page_number], $line_start, "\0"),
            $line_unpacked['data'],
            $line_start,
            strlen($line_unpacked['data'])
          );

          break;
        case HexLineType::END:
          $done = true;
          break;
        case HexLineType::PAGE:
          $page_number =
              unpack('npage_number', $line_unpacked['data'])['page_number'];

          if (!array_key_exists($page_number, $application->pages)) {
            $application->pages[$page_number] = '';
          }

          break;
      }

      $line = strtok("\r\n");
    }

    if (!$done) {
      throw new \OutOfBoundsException('End block not found');
    }

    return $application;
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
      FLASH_FILE_SIGNATURE,
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

  private static function dataFromHex($data_hex) {
    if ($data_hex[0] !== ':') {
      throw new \OutOfBoundsException("Invalid hex line $data_hex");
    }

    $line_packed = hex2bin(substr($data_hex, 1));

    if ($line_packed === false || strlen($line_packed) < 5) {
      throw new \OutOfBoundsException("Invalid hex line $data_hex");
    }

    if (array_sum(unpack('C*', $line_packed)) & 0xff !== 0) {
      throw new \OutOfBoundsException("Incorrect checksum on $data_hex");
    }

    $line_unpacked = unpack(
      'Cdata_length/naddress/Ctype/a*data',
      substr($line_packed, 0, -1)
    );

    if ($line_unpacked['data_length'] > strlen($line_unpacked['data'])) {
      throw new \OutOfBoundsException("Hex data length exceeds line length");
    }

    $line_unpacked['data'] =
        substr($line_unpacked['data'], 0, $line_unpacked['data_length']);
    unset($line_unpacked['data_length']);
    return $line_unpacked;
  }

  private static function dataToHex($address, $type, $data) {
    $line_packed = pack('CnCa*', strlen($data), $address, $type, $data);

    return ':' . strtoupper(
      bin2hex($line_packed . chr(-array_sum(unpack('C*', $line_packed))))
    ) . "\r\n";
  }

  private static function validateIndex($page_number) {
    if ($page_number < 0 || $page_number !== (int)$page_number) {
      throw new \OutOfRangeException(
        "Page number $page_number must be a nonnegative integer"
      );
    }
  }

  private static function validateInteger($string, $description) {
    if (!preg_match('/^\d+$/', $string)) {
      throw new \OutOfBoundsException("Invalid $description $string");
    }

    return (int)$string;
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
