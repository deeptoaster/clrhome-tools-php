<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * An application.
 */
class Application extends Variable {
  private $contents = '';

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
   * Returns the application contents as a byte string.
   */
  public function getContents() {
    return $this->contents;
  }

  /**
   * Sets the application contents as a byte string.
   * @param string $contents The application contents to set as a byte string.
   */
  public function setContents($contents) {
    $this->contents = $contents;
  }
}
?>
