<?
namespace ClrHome;

include(__DIR__ . '/common.php');

abstract class VariableType extends Enum {
  const PROGRAM = 0x05;
  const PROGRAM_LOCKED = 0x06;
}

abstract class Variable {
  private $archived = false;
  private $series = Series::TI83P;
  private $version = 0;

  abstract protected function getData();

  abstract protected function getName();

  abstract protected function getType();

  final public static function fromFile($file_name) {
    $packed = file_get_contents($file_name);

    if ($packed === false) {
      throw new \UnderflowException(
        "Unable to read variable file at $file_name"
      );
    }

    return $this->fromString($packed);
  }

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

      if ($header_length !== 11 || $header_length !== 13) {
        throw new \OutOfBoundsException('Unrecognized header format');
      }

      $buffer_length = self::readWord($packed, $entry_start + 2);

      if ($entry_start + $header_length + $buffer_length + 4 > strlen($packed)) {
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
        case VariableType::PROGRAM:
        case VariableType::PROGRAM_LOCKED:
          $variable = new Program();

          if ($type === VariableType::PROGRAM_LOCKED) {
            $variable->setEditable(false);
          }

          $variable->setName($name);
          $tokens_length = self::readWord($data, 0);

          if ($tokens_length + 2 > $data_length) {
            throw new \OutOfBoundsException(
              'Program body length exceeds variable data length'
            );
          }

          $variable->setBody(substr($data, 2, $tokens_length));
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
    }

    return $variables;
  }

  private static function readWord($string, $offset) {
    return (ord($string[$offset + 1]) << 8) + ord($string[$offset]);
  }

  final public function getArchived() {
    return $this->archived;
  }

  final public function setArchived($archived) {
    $this->archived = (bool)$archived;
  }

  final public function getSeries() {
    return $this->series;
  }

  final public function setSeries($series) {
    $this->series = Series::validate($series);
  }

  final public function getVersion() {
    return $this->version;
  }

  final public function setVersion($version) {
    $this->version = (int)$version;
  }

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

    switch ($series !== null ? $series : $this->series) {
      case Series::TI83:
        return pack(
          'v2Ca8va*',
          11,
          strlen($data),
          $this->getType(),
          $this->getName(),
          strlen($data),
          $data
        );
      case Series::TI83P:
        return pack(
          'v2Ca8C2va*',
          13,
          strlen($data),
          $this->getType(),
          $this->getName(),
          $this->version,
          $this->archived ? 0x80 : 0x00,
          strlen($data),
          $data
        );
    }
  }
}
