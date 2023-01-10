<?php
namespace ClrHome;

include_once(__DIR__ . '/TiObject.class.php');

/**
 * An enum representing the calculator series to target during export.
 */
abstract class VariableSeries extends Enum {
  const TI83 = '**TI83**';
  const TI83P = '**TI83F*';
}

/**
 * A TI variable that can be loaded into RAM and grouped.
 */
abstract class Variable extends TiObject {
  private $archived = false;
  private $series = VariableSeries::TI83P;
  private $version = 0;

  abstract protected static function fromEntry($type, $name, $data);

  abstract protected function getData();

  /**
   * Returns an array of variables constructed from a string.
   * @param string $packed A string in TI variable file format.
   * @param number $limit The maximum number of TiObjects to read.
   */
  final public static function fromString($packed, $limit = -1) {
    switch (substr($packed, 0, 11)) {
      case VariableSeries::TI83 . "\x1a\x0a\x00":
        $series = VariableSeries::TI83;
        break;
      case VariableSeries::TI83P . "\x1a\x0a\x00":
        $series = VariableSeries::TI83P;
        break;
      default:
        throw new \OutOfBoundsException('Invalid variable file format');
    }

    $packed_length = self::readWord($packed, 53) + 55;
    $variables = array();
    $entry_start = 55;

    while (
      $entry_start < $packed_length &&
          ($limit < 0 || count($variables) < $limit)
    ) {
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
        case VariableType::MATRIX:
          $variable = Matrix::fromEntry($type, $name, $data);
          break;
        case VariableType::PICTURE:
          $variable = Picture::fromEntry($type, $name, $data);
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
      array_sum(unpack('C*', $entries))
    );
  }

  /**
   * Writes one or more RAM variables to a TI variable file.
   * @param array<Variable> $variables The list of variables to write.
   * @param string $file_name The path of the file to write.
   * @param string $comment An optional comment to include in the file.
   */
  final public static function variablesToFile(
    $variables,
    $file_name,
    $comment = ''
  ) {
    $variables[0]->toFile($file_name, $comment, array_slice($variables, 1));
  }

  /**
   * Returns one or more RAM variables in TI variable file format as a string.
   * @param array<Variable> $variables The list of variables to write.
   * @param string $comment An optional comment to include in the file.
   */
  final public static function variablesToString($variables, $comment = '') {
    return $variables[0]->toString($comment, array_slice($variables, 1));
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
   * @param VariableSeries $series The calculator series to target.
   */
  final public function setSeries($series) {
    $this->series = VariableSeries::validate($series);
  }

  /**
   * Gets the version number of this variable format.
   */
  final public function getVersion() {
    return $this->version;
  }

  /**
   * Sets the version number of this variable format.
   * @param number $version The variable format version.
   */
  final public function setVersion($version) {
    $this->version = (int)$version;
  }

  private function getEntry($series = null) {
    $data = $this->getData();
    $type = $this->getType();

    if ($this->name === null) {
      $slash_position = strrpos(static::class, '\\');

      throw new \BadFunctionCallException((
        $slash_position !== false
          ? substr(static::class, $slash_position + 1)
          : static::class
      ) . ' name must be set before export');
    }

    switch ($series !== null ? $series : $this->series) {
      case VariableSeries::TI83:
        return pack(
          'v2Ca8va*',
          11,
          strlen($data),
          $type,
          $this->name,
          strlen($data),
          $data
        );
      case VariableSeries::TI83P:
        return pack(
          'v2Ca8C2va*',
          13,
          strlen($data),
          $type,
          $this->name,
          $this->version,
          $this->archived ? 0x80 : 0x00,
          strlen($data),
          $data
        );
    }
  }
}
?>
