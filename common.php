<?
namespace ClrHome;

/**
 * A generic enumerator implementation modelled after SplEnum.
 */
abstract class Enum {
  /**
   * Returns a map of enum names to values as an associative array.
   */
  public static function getConstList() {
    static $const_list = null;

    if ($const_list === null) {
      $const_list = array();

      for (
        $reflection = new \ReflectionClass(static::class);
        $reflection !== false;
        $reflection = $reflection->getParentClass()
      ) {
        $const_list = array_merge($reflection->getConstants(), $const_list);
      }
    }

    return $const_list;
  }

  /**
   * Validates and returns a provided enum value, or throws if invalid.
   * @param Enum $value The enum value to validate.
   */
  public static function validate($value) {
    if (!in_array($value, static::getConstList())) {
      throw new \InvalidArgumentException(
        sprintf('Invalid value for %s: %s', static::class, $value)
      );
    }

    return $value;
  }
}
?>
