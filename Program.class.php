<?php
namespace ClrHome;

define('ClrHome\PROGRAM_CATALOG_URL', 'https://clrhome.org/catalog/?alt=xml');

include_once(__DIR__ . '/Variable.class.php');

abstract class Language extends Enum {
  const AXE = 'axe';
  const BASIC = '';
  const GRAMMER = 'grammer';
}

/**
 * A TI program.
 */
class Program extends Variable {
  private $body = '';
  private $catalog;
  private $catalogFile;
  private $editable = true;
  private $inverseCatalog;
  private $language = Language::BASIC;

  public function __construct() {
    $this->catalogFile = __DIR__ . '/catalog.xml';
  }

  final protected static function fromEntry($type, $name, $data) {
    $program = new static();
    $program->name = $name;

    if (strlen($data) < 2) {
      throw new \OutOfBoundsException('Program contents not found');
    }

    if ($type === VariableType::PROGRAM_LOCKED) {
      $program->setEditable(false);
    }

    $tokens_length = parent::readWord($data, 0);

    if ($tokens_length + 2 > strlen($data)) {
      throw new \OutOfBoundsException(
        'Program body length exceeds variable data length'
      );
    }

    $program->setBody(substr($data, 2, $tokens_length));
    return $program;
  }

  /**
   * Returns the program name detokenized to ASCII.
   */
  final public function getName() {
    return isset($this->name) ? str_replace('[', 'theta', $this->name) : null;
  }

  /**
   * Sets the program name as an ASCII string.
   * @param string $name The program name as an ASCII string.
   */
  final public function setName($name) {
    if (!preg_match('/^([A-Z\[]|theta)([0-9A-Z\[]|theta)*$/', $name)) {
      throw new \InvalidArgumentException("Invalid program name $name");
    }

    $this->name = substr(str_replace('theta', '[', $name), 0, 8);
  }

  final public function getType() {
    return $this->getEditable()
      ? VariableType::PROGRAM
      : VariableType::PROGRAM_LOCKED;
  }

  final protected function getData() {
    $body = $this->getBody();
    return pack('va*', strlen($body), $body);
  }

  /**
   * Returns the program body as a token string.
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * Sets the program body as a token string.
   * @param string $tokens The program body as a token string.
   */
  public function setBody($tokens) {
    $this->body = $tokens;
  }

  /**
   * Returns the program body detokenized to ASCII.
   */
  public function getBodyAsChars() {
    if (!isset($this->catalog)) {
      $this->initializeCatalog();
    }

    $namespace = $this->getLanguage() !== Language::BASIC
      ? $this->catalog->getDocNamespaces()
      : '';
    $chars = '';
    $line_chars = '';
    $line_tokens = '';
    $token_start = 0;

    while ($token_start <= strlen($this->body)) {
      if ($token_start < strlen($this->body)) {
        list(
          $token_sequence,
          $token_chars
        ) = $this->getNextToken($namespace, 'id', $this->body, $token_start);
      }

      if (
        $token_start === strlen($this->body) || (string)$token_chars === "\n"
      ) {
        $normalized_line_chars = preg_replace('/(.)\\\\/', '$1', $line_chars);

        if ($this->tokenize($normalized_line_chars) === $line_tokens) {
          $chars .= $normalized_line_chars;
        } else {
          $slash_position = strpos($line_chars, '\\');

          while (true) {
            $test_end = strpos($line_chars . "\n", '\\', $slash_position + 2);

            if ($test_end === false) {
              $chars .= substr($line_chars, 0, $slash_position);
              break;
            }

            $test_chars = substr($line_chars, 0, $slash_position) . substr(
              $line_chars,
              $slash_position + 1,
              $test_end - $slash_position - 1
            );

            $test_tokens = $this->tokenize($test_chars);

            if (
              $test_tokens === substr($line_tokens, 0, strlen($test_tokens))
            ) {
              $slash_position = $test_end - 1;
              $line_chars = $test_chars . substr($line_chars, $test_end);
            } else {
              $group_chars = substr($line_chars, 0, $slash_position + 1);
              $group_tokens = $this->tokenize($group_chars);
              $chars .= $group_chars;
              $line_chars = substr($line_chars, $slash_position + 1);
              $line_tokens = substr($line_tokens, strlen($group_tokens));
              $slash_position = $test_end - $slash_position - 1;
            }
          }
        }

        $line_chars = '';
        $line_tokens = '';

        if ($token_start !== strlen($this->body)) {
          $chars .= "\n";
        }

        $token_start++;
      } else {
        $line_chars .= "$token_chars\\";
        $line_tokens .= $token_sequence;
        $token_start += strlen($token_sequence);
      }
    }

    return $chars;
  }

  /**
   * Returns the program body detokenized to TI device characters.
   */
  public function getBodyAsTiChars() {
    if (!isset($this->catalog)) {
      $this->initializeCatalog();
    }

    $namespace = $this->getLanguage() !== Language::BASIC
      ? $this->catalog->getDocNamespaces()
      : '';
    $chars = '';
    $token_start = 0;

    while ($token_start < strlen($this->body)) {
      list(
        $token_sequence,
        $token_chars
      ) = $this->getNextToken($namespace, 'chars', $this->body, $token_start);

      $chars .= preg_replace_callback(
        '/\\\\(x[\da-z]{2})?/',
        function($match) {
          return strlen($match[0]) === 4
            ? chr(hexdec(substr($match[1], 1)))
            : '\\';
        },
        $token_chars
      );

      $token_start += strlen($token_sequence);
    }

    return $chars;
  }

  /**
   * Sets the program body by tokenizing an ASCII string.
   * @param string $chars The program body as an ASCII string.
   */
  public function setBodyAsChars($chars) {
    $this->body = $this->tokenize($chars);
  }

  /**
   * Returns the path of the catalog file to use for tokenizing.
   */
  final public function getCatalogFile() {
    return $this->catalogFile;
  }

  /**
   * Sets the path of the catalog file to use for tokenizing.
   * @param string $catalog_file The path of the catalog file to use.
   */
  final public function setCatalogFile($catalog_file) {
    if ($this->catalogFile !== $catalog_file) {
      $this->catalogFile = $catalog_file;
      $this->catalog = null;
      $this->inverseCatalog = null;
    }
  }

  /**
   * Returns whether this program should be editable on the calculator.
   */
  public function getEditable() {
    return $this->editable;
  }

  /**
   * Sets whether this program should be editable on the calculator.
   * @param bool $editable Whether this program should be editable.
   */
  public function setEditable($editable) {
    $this->editable = (bool)$editable;
  }

  /**
   * Returns the language to target while tokenizing.
   */
  public function getLanguage() {
    return $this->language;
  }

  /**
   * Sets the language to target while tokenizing.
   * @param Language $language The language to target while tokenizing.
   */
  public function setLanguage($language) {
    $this->language = Language::validate($language);
  }

  private function getNextToken(
    $namespace,
    $attribute,
    $tokens,
    $token_start
  ) {
    $token_sequence = $tokens[$token_start];
    $token = $this->catalog->children()[ord($token_sequence)];

    if ($token->getName() === 'table') {
      if ($token_start + 1 === strlen($tokens)) {
        throw new \UnexpectedValueException(
          'Missing second byte in two-byte token sequence'
        );
      }

      $second_byte = $tokens[$token_start + 1];
      $token_sequence .= $second_byte;
      $token = $token->children()[ord($second_byte)];
    }

    if ($token === null || $token['id'] === null) {
      throw new \UnexpectedValueException(
        'Unrecognized token ' . strtoupper(bin2hex($token_sequence))
      );
    }

    $token_chars = $token->attributes($namespace)[$attribute];

    return array(
      $token_sequence,
      $token_chars !== null ? $token_chars : $token[$attribute]
    );
  }

  private function initializeCatalog() {
    if (!file_exists($this->catalogFile)) {
      $catalog_handle = fopen(PROGRAM_CATALOG_URL, 'r');

      if ($catalog_handle === false) {
        throw new \UnderflowException(
          'Unable to download catalog file at ' . PROGRAM_CATALOG_URL
        );
      }

      if (file_put_contents($this->catalogFile, $catalog_handle) === false) {
        throw new \UnderflowException(
          "Unable to write catalog file at $this->catalogFile"
        );
      }
    }

    $this->catalog = simplexml_load_file($this->catalogFile);
    $namespaces = $this->catalog->getDocNamespaces();

    if ($this->catalog === false) {
      throw new \UnderflowException(
        "Unable to read catalog file at $this->catalogFile"
      );
    }

    $token_index = 0;

    foreach ($this->catalog->children() as $token) {
      switch ($token->getName()) {
        case 'table':
          $subtoken_index = 0;

          foreach ($token->children() as $subtoken) {
            $this->registerToken(
              $namespaces,
              $subtoken,
              $token_index,
              $subtoken_index
            );

            $subtoken_index++;
          }

          break;
        case 'token':
          $this->registerToken(
            $namespaces,
            $token,
            $token_index
          );

          break;
      }

      $token_index++;
    }
  }

  private function registerToken(
    $namespaces,
    $token,
    $token_index,
    $subtoken_index = null
  ) {
    foreach ($namespaces as $prefix => $namespace) {
      $id = $token->attributes(
        $prefix !== Language::BASIC ? $namespace : ''
      )['id'];

      if ($id !== null) {
        $this->inverseCatalog[(string)$id === '\\\\' ? ":\t" : ":$id"] =
            chr($token_index) .
            ($subtoken_index !== null ? chr($subtoken_index) : '');
      }
    }
  }

  private function tokenize($chars) {
    if (!isset($this->inverseCatalog)) {
      $this->initializeCatalog();
    }

    $tokens = '';

    $chars = str_replace(
      array("\xe2\x86\x92", '\\\\'),
      array('->', "\t"),
      $chars
    );

    for (
      $group_start = 0;
      $group_start < strlen($chars);
      $group_start = $group_end + 1
    ) {
      $newline_position = strpos($chars, "\n", $group_start);
      $slash_position = strpos($chars, '\\', $group_start);

      $group_end = min(
        $newline_position !== false ? $newline_position : strlen($chars),
        $slash_position !== false ? $slash_position : strlen($chars)
      );

      $token_stack = array();
      $token_start = $group_start;
      $group_tokens = array();
      $visited = array();

      while ($token_start !== $group_end) {
        if (isset($token_stack[$token_start])) {
          if (count($token_stack[$token_start]) === 0) {
            if (empty($token_stack)) {
              break;
            } else {
              $visited[$token_start] = true;
              array_pop($token_stack);
              $token_start -= strlen(array_pop($group_tokens));
            }
          } else {
            $token_chars = array_shift($token_stack[$token_start]);
            $token_end = $token_start + strlen($token_chars);

            if (!isset($visited[$token_end])) {
              $group_tokens[] = $token_chars;
              $token_start = $token_end;
            }
          }
        } else {
          $token_stack[$token_start] = array();

          for (
            $token_length = min($group_end - $token_start, 14);
            $token_length > 0;
            $token_length--
          ) {
            $token_chars = substr($chars, $token_start, $token_length);

            if (isset($this->inverseCatalog[":$token_chars"])) {
              $token_stack[$token_start][] = $token_chars;
            }
          }
        }
      }

      if ($token_start === $group_start) {
        throw new \UnexpectedValueException(
          'Unable to parse token near ' . substr(
            $chars,
            $group_start,
            $group_end - $group_start
          )
        );
      }

      $tokens = array_reduce($group_tokens, function($tokens, $token_chars) {
        return $tokens . $this->inverseCatalog[":$token_chars"];
      }, $tokens);

      if ($group_end < strlen($chars) && $chars[$group_end] === "\n") {
        $tokens .= $this->inverseCatalog[":\n"];
      }
    }

    return $tokens;
  }
}
?>
