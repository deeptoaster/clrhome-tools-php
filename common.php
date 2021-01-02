<?
namespace ClrHome;

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
?>
