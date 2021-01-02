<?
namespace ClrHome;

include_once(__DIR__ . '/Variable.class.php');

/**
 * A TI appvar.
 */
class AppVar extends Variable {
  private $body = '';
  private $name;

  final public function getData() {
    $body = $this->getBody();
    return pack('va*', strlen($body), $body);
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

  /**
   * Returns the appvar content.
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * Sets the appvar content.
   * @param string $body The appvar content.
   */
  public function setBody($body) {
    $this->body = $body;
  }
}
?>
