<?
namespace ClrHome;

define('PICTURE_COLUMN_COUNT', 96);
define('PICTURE_ROW_COUNT', 63);

include_once(__DIR__ . '/Variable.class.php');

/**
 * A picture variable.
 */
class Picture extends Variable implements \ArrayAccess {
  private $buffer;
  private $name;

  final protected static function fromEntry($type, $name, $data) {
    $picture = new static();
    $picture->name = str_pad($name, 2, "\x00");

    if (strlen($data) < 2) {
      throw new \OutOfBoundsException('Matrix contents not found');
    }

    $pixels_length = parent::readWord($data, 0);

    if ($pixels_length / 8 + 2 > strlen($data)) {
      throw new \OutOfBoundsException(
        'Picture length exceeds variable data length'
      );
    }

    $picture->setBuffer(substr($data, 2, $pixels_length));
    return $picture;
  }

  public function __construct() {
    $this->buffer =
        str_repeat("\x00", PICTURE_ROW_COUNT * PICTURE_COLUMN_COUNT / 8);
  }

  /**
   * Returns the picture name detokenized to ASCII.
   */
  final public function getName() {
    return isset($this->name)
      ? ord($this->name[1]) === 0x09
        ? 'Pic0'
        : 'Pic' . (string)(ord($this->name[1]) + 0x01)
      : null;
  }

  /**
   * Sets the picture name as a single token as an ASCII string.
   * @param string $name One of 'Pic1' through 'Pic9' or 'Pic0'.
   */
  final public function setName($name) {
    if (!preg_match('/^Pic\d$/', $name)) {
      throw new \InvalidArgumentException("Invalid picture name $name");
    }

    $this->name =
        pack('C2', 0x60, $name[3] === '0' ? 0x09 : (int)$name[3] - 0x01);
  }

  final public function getType() {
    return VariableType::PICTURE;
  }

  final protected function getData() {
    return pack(
      'va*',
      PICTURE_ROW_COUNT * PICTURE_COLUMN_COUNT / 8,
      $this->getBuffer()
    );
  }

  public function offsetExists($index) {
    list($row, $column) = self::validateIndex($index);
    return $row < PICTURE_ROW_COUNT &&
        $column < PICTURE_COLUMN_COUNT;
  }

  public function offsetGet($index) {
    list($row, $column) = self::validateIndex($index);

    $byte = ord(
      $this->buffer[(int)($row * PICTURE_COLUMN_COUNT / 8 + $column / 8)]
    );

    return ($byte << $column % 8 & 0x80) === 0x80;
  }

  public function offsetSet($index, $value) {
    list($row, $column) = self::validateIndex($index);
    $mask = 0x80 >> $column % 8;
    $byte_index = (int)($row * PICTURE_COLUMN_COUNT / 8 + $column / 8);
    $byte = ord($this->buffer[$byte_index]);
    $this->buffer[$byte_index] =
        chr((bool)$value === true ? $byte | $mask : $byte & ~$mask);
  }

  public function offsetUnset($index) {
    $this->offsetSet(false);
  }

  private static function validateIndex($index) {
    if (!preg_match('/^(\d+),(\d+)$/', $index, $matches)) {
      throw new \OutOfRangeException(
        "Pixel index $index must be integers in the form row,column"
      );
    }

    return array((int)$matches[1], (int)$matches[2]);
  }

  /**
   * Returns the raw picture contents.
   */
  public function getBuffer() {
    return $this->buffer;
  }

  /**
   * Sets the raw picture contents.
   * @param string $buffer The picture contents.
   */
  public function setBuffer($buffer) {
    $this->buffer = str_pad(
      substr($buffer, 0, PICTURE_ROW_COUNT * PICTURE_COLUMN_COUNT / 8),
      PICTURE_ROW_COUNT * PICTURE_COLUMN_COUNT / 8,
      "\x00"
    );
  }
}
?>
