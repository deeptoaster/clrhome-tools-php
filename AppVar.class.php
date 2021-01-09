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
    $appvar->setName($name);
    $appvar->setData($data);
    return $appvar;
  }

  final public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
  }

  /**
   * Returns the appvar name as a character string.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Sets the appvar name as a character string.
   * @param string $name The appvar name as a character string.
   */
  public function setName($name) {
    $this->name = substr($name, 0, 8);
  }

  final public function getType() {
    return VariableType::APPVAR;
  }
}
?>
