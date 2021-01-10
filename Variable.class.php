<?
namespace ClrHome;

include(__DIR__ . '/common.php');

/**
 * An enum representing the variable type.
 */
abstract class VariableType extends Enum {
  const REAL = 0x00;
  const LIST_REAL = 0x01;
  const PROGRAM = 0x05;
  const PROGRAM_LOCKED = 0x06;
  const COMPLEX = 0x0c;
  const LIST_COMPLEX = 0x0d;
  const APPVAR = 0x15;
}

/**
 * A TI variable.
 */
abstract class Variable {
  private $archived = false;
  private $series = Series::TI83P;
  private $version = 0;

  abstract protected static function fromEntry($type, $name, $data);

  abstract public function getName();

  abstract public function getType();

  abstract protected function getData();

  /**
   * Returns an array of variables constructed from a TI variable file.
   * @param string $file_name The path of the file to read as a string.
   */
  final public static function fromFile($file_name) {
    $packed = file_get_contents($file_name);

    if ($packed === false) {
      throw new \UnderflowException(
        "Unable to read variable file at $file_name"
      );
    }

    return $this->fromString($packed);
  }

  /**
   * Returns an array of variables constructed from a string.
   * @param string $packed A string in TI variable file format.
   */
  final public static function fromString($packed) {
    switch (substr($packed, 0, 11)) {
      case Series::TI83 . "\x1a\x0a\x00":
        $series = Series::TI83;
        break;
      case Series::TI83P . "\x1a\x0a\x00":
        $series = Series::TI83P;
        break;
      default:
        throw new \OutOfBoundsException('Invalid variable file format');
    }

    $packed_length = self::readWord($packed, 53) + 55;
    $variables = array();
    $entry_start = 55;

    while ($entry_start < $packed_length) {
      $header_length = self::readWord($packed, $entry_start);

      if ($header_length !== 11 && $header_length !== 13) {
        throw new \OutOfBoundsException('Unrecognized header format');
      }

      $buffer_length = self::readWord($packed, $entry_start + 2);

      if (
        $entry_start + $header_length + $buffer_length + 4 > strlen($packed)
      ) {
        throw new \OutOfBoundsException(
          'Variable entry length exceeds file length'
        );
      }

      $data_length =
          self::readWord($packed, $entry_start + $header_length + 2);

      if ($data_length > $buffer_length) {
        throw new \OutOfBoundsException(
          'Variable data length exceeds variable entry length'
        );
      }

      $type = ord($packed[$entry_start + 4]);
      $name = rtrim(substr($packed, $entry_start + 5, 8), "\x00");
      $data = substr($packed, $entry_start + $header_length + 4, $data_length);

      switch ($type) {
        case VariableType::APPVAR:
          $variable = AppVar::fromEntry($type, $name, $data);
          break;
        case VariableType::COMPLEX:
        case VariableType::REAL:
          $variable = Number::fromEntry($type, $name, $data);
          break;
        case VariableType::LIST_COMPLEX:
        case VariableType::LIST_REAL:
          $variable = ListVariable::fromEntry($type, $name, $data);
          break;
        case VariableType::PROGRAM:
        case VariableType::PROGRAM_LOCKED:
          $variable = Program::fromEntry($type, $name, $data);
          break;
        default:
          $variable = null;
          break;
      }

      if ($variable !== null && $header_length === 13) {
        $variable->setVersion(ord($packed[$entry_start + 13]));
        $variable->setArchived(ord($packed[$entry_start + 14]) & 0x80);
      }

      $variables[] = $variable;
      $entry_start += $header_length + $buffer_length + 4;
    }

    return $variables;
  }

  final protected static function floatingPointToNumber($packed) {
    if (ord($packed[0]) & 0x02 !== 0) {
      return array(null, null);
    }

    $imaginary = strlen($packed) > 9 && (ord($packed[9]) & 0x0c) !== 0
      ? self::floatingPointToNumber(substr($packed, 9))
      : array(null);
    $unpacked = unpack('C2exponent/H14mantissa', $packed);
    $real =
        ($unpacked['mantissa'][0] . '.' . substr($unpacked['mantissa'], 1)) *
        pow(10, $unpacked['exponent2'] - 0x80);

    return array(
      $unpacked['exponent1'] & 0x80 !== 0 ? -$real : $real,
      $imaginary[0]
    );
  }

  final protected static function numberToFloatingPoint(
    $real = null,
    $imaginary = null,
    $forceComplex = false
  ) {
    if ($real === null) {
      return pack('C9', 0x02, 0, 0, 0, 0, 0, 0, 0, 0);
    }

    $exponent = $real !== 0 ? (int)log10($real) : 0;

    $mantissa = str_pad(
      str_replace('.', '', (string)($real / pow(10, $exponent))),
      14,
      '0'
    );

    $packed = pack(
      'C2H14',
      $real < 0 ? 0x80 : 0x00,
      $exponent + 0x80,
      $mantissa
    );

    if ($imaginary !== null && $imaginary !== 0 || $forceComplex) {
      $packed .=
          self::numberToFloatingPoint($imaginary !== null ? $imaginary : 0);
      $packed[0] = chr(ord($packed[0]) | 0x0c);
      $packed[9] = chr(ord($packed[9]) | 0x0c);
    }

    return $packed;
  }

  final protected static function readWord($string, $offset) {
    return (ord($string[$offset + 1]) << 8) + ord($string[$offset]);
  }

  /**
   * Returns whether or not this variable should be archived (`TI83P` only).
   */
  final public function getArchived() {
    return $this->archived;
  }

  /**
   * Sets whether or not this variable should be archived (`TI83P` only).
   * @param bool $archived Whether this variable should be archived.
   */
  final public function setArchived($archived) {
    $this->archived = (bool)$archived;
  }

  /**
   * Returns the calculator series this variable should target during export.
   */
  final public function getSeries() {
    return $this->series;
  }

  /**
   * Sets the calculator series this variable should target during export.
   * @param Series $series The calculator series this variable should target.
   */
  final public function setSeries($series) {
    $this->series = Series::validate($series);
  }

  final public function getVersion() {
    return $this->version;
  }

  final public function setVersion($version) {
    $this->version = (int)$version;
  }

  /**
   * Writes one or more variables to a TI variable file.
   * @param string $file_name The path of the file to write.
   * @param string $comment An optional comment to include in the file.
   * @param array<Variable> $includes Additional variables to include.
   */
  final public function toFile(
    $file_name,
    $comment = '',
    $includes = array()
  ) {
    if (
      file_put_contents($file_name, $this->toString($comment, $includes)) ===
          false
    ) {
      throw new \UnderflowException(
        "Unable to write variable file at $file_name"
      );
    }
  }

  /**
   * Returns one or more variables in TI variable file format as a string.
   * @param string $comment An optional comment to include in the file.
   * @param array<Variable> $includes Additional variables to include.
   */
  final public function toString($comment = '', $includes = array()) {
    $entries = array_reduce($includes, function($entries, $include) {
      return $entries . $include->getEntry($this->series);
    }, $this->getEntry());

    return pack(
      'a8C3A42va*v',
      $this->series,
      0x1a, 0x0a, 0x00,
      $comment,
      strlen($entries),
      $entries,
      array_sum(array_map('ord', str_split($entries)))
    );
  }

  final protected function getEntry($series = null) {
    $data = $this->getData();
    $name = $this->getName();
    $type = $this->getType();

    if ($name === null) {
      throw new \BadFunctionCallException(
        'Variable name must be set before export'
      );
    }

    switch ($series !== null ? $series : $this->series) {
      case Series::TI83:
        return pack(
          'v2Ca8va*',
          11,
          strlen($data),
          $type,
          $name,
          strlen($data),
          $data
        );
      case Series::TI83P:
        return pack(
          'v2Ca8C2va*',
          13,
          strlen($data),
          $type,
          $name,
          $this->version,
          $this->archived ? 0x80 : 0x00,
          strlen($data),
          $data
        );
    }
  }
}
