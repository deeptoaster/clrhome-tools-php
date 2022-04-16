<?
namespace ClrHome;

include(__DIR__ . '/common.php');

/**
 * An enum representing the variable type.
 */
abstract class VariableType extends Enum {
  const REAL = 0x00;
  const LIST_REAL = 0x01;
  const MATRIX = 0x02;
  const PROGRAM = 0x05;
  const PROGRAM_LOCKED = 0x06;
  const PICTURE = 0x07;
  const COMPLEX = 0x0c;
  const LIST_COMPLEX = 0x0d;
  const APPVAR = 0x15;
  const APPLICATION = 0xff;
}

/**
 * A TI variable.
 */
abstract class Variable {
  protected $name;

  abstract public function getName();

  abstract public function setName($name);

  abstract public function getType();

  abstract public static function fromString($packed);

  abstract public function toString($comment = '');

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

    return self::fromString($packed);
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
}
