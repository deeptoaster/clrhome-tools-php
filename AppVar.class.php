<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * A TI appvar.
 */
class AppVar extends Variable {
  private $data = '';
  private $name;

  final protected static function fromEntry($type, $name, $data) {
    $appvar = new static();
    $appvar->name = $name;
    $appvar->data = $data;
    return $appvar;
  }

  public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
  }

  /**
   * Returns the appvar name as a character string.
   */
  final public function getName() {
    return isset($this->name) ? $this->name : null;
  }

  /**
   * Sets the appvar name as a character string.
   * @param string $name The appvar name as a character string.
   */
  final public function setName($name) {
    $this->name = substr($name, 0, 8);
  }

  final public function getType() {
    return VariableType::APPVAR;
  }
}
?>
