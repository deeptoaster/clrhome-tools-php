<?
namespace ClrHome;

// TODO: move from conf.xml to catalog.xml
define('TOKENIZER_CATALOG_URL', 'https://clrhome.org/catalog/conf.xml');

include(__DIR__ . '/Variable.class.php');

abstract class Language extends Enum {
  const AXE = 'axe';
  const BASIC = '';
  const GRAMMER = 'grammer';
}

class Program extends Variable {
  private $body = '';
  private $catalog;
  private $catalogFile = __DIR__ . '/catalog.xml';
  private $editable = true;
  private $inverseCatalog;
  private $language = Language::BASIC;
  private $name;

  final public function getData() {
    $body = $this->getBodyAsTokens();
    return pack('va*', strlen($body), $body);
  }

  public function getName() {
    return $this->name;
  }

  public function setName($name) {
    if (!preg_match('/^([A-Z\[]|theta)([0-9A-Z\[]|theta){0,7}', $name)) {
      throw new \InvalidArgumentException('Invalid program name');
    }

    $this->name = str_replace('theta', '[', $name);
  }

  final public function getType() {
    return $this->getEditable()
      ? VariableType::PROGRAM
      : VariableType::PROGRAM_LOCKED;
  }

  public function getBodyAsChars() {
    return $this->detokenize($this->body);
  }

  public function setBodyAsChars($chars) {
    $this->body = $this->tokenize($chars);
  }

  public function getBodyAsTokens() {
    return $this->body;
  }

  public function setBodyAsTokens($tokens) {
    $this->body = $tokens;
  }

  final public function getCatalogFile() {
    return $this->catalogFile;
  }

  final public function setCatalogFile($catalog_file) {
    if ($this->catalogFile !== $catalog_file) {
      $this->catalogFile = $catalog_file;
      $this->catalog = null;
      $this->inverseCatalog = null;
    }
  }

  public function getEditable() {
    return $this->editable;
  }

  public function setEditable($editable) {
    $this->editable = (bool)$editable;
  }

  public function getLanguage() {
    return $this->language;
  }

  public function setLanguage($language) {
    $this->language = Language::validate($language);
  }

  private function detokenize($tokens) {
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

    while ($token_start <= strlen($tokens)) {
      if ($token_start < strlen($tokens)) {
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

        $token_chars = $token->attributes($namespace)['id'];
        $token_chars = $token_chars !== null ? $token_chars : $token['id'];
      }

      if ($token_start === strlen($tokens) || (string)$token_chars === "\n") {
        $yolo = str_replace('\\', '', $line_chars);

        if ($this->tokenize($yolo) === $line_tokens) {
          $chars .= $yolo;
        } else {
          $slash_position = strpos($line_chars, '\\');

          while (true) {
            $test_end = strpos($line_chars, '\\', $slash_position + 1);

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

        if ($token_start !== strlen($tokens)) {
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

  private function initializeCatalog() {
    if (!file_exists($this->catalogFile)) {
      $catalog_handle = fopen(TOKENIZER_CATALOG_URL, 'r');

      if ($catalog_handle === false) {
        throw new \OutOfBoundsException(
          'Unable to download catalog file at ' . TOKENIZER_CATALOG_URL
        );
      }

      if (file_put_contents($this->catalogFile, $catalog_handle) === false) {
        throw new \OutOfBoundsException(
          "Unable to write catalog file at $this->catalogFile"
        );
      }
    }

    $this->catalog = simplexml_load_file($this->catalogFile);
    $namespaces = $this->catalog->getDocNamespaces();

    if ($this->catalog === false) {
      throw new \OutOfBoundsException(
        "Unable to read catalog file at $this->catalogFile"
      );
    }

    $token_idx = 0;

    foreach ($this->catalog->children() as $token) {
      switch ($token->getName()) {
        case 'table':
          $subtoken_idx = 0;

          foreach ($token->children() as $subtoken) {
            $this->registerToken(
              $namespaces,
              $subtoken,
              $token_idx,
              $subtoken_idx
            );

            $subtoken_idx++;
          }

          break;
        case 'token':
          $this->registerToken(
            $namespaces,
            $token,
            $token_idx
          );

          break;
      }

      $token_idx++;
    }
  }

  private function registerToken(
    $namespaces,
    $token,
    $token_idx,
    $subtoken_idx = null
  ) {
    foreach ($namespaces as $prefix => $namespace) {
      $id = $token->attributes(
        $prefix !== Language::BASIC ? $namespace : ''
      )['id'];

      if ($id !== null) {
        $this->inverseCatalog[":$id"] = chr($token_idx) . (
          $subtoken_idx !== null ? chr($subtoken_idx) : ''
        );
      }
    }
  }

  private function tokenize($chars) {
    if (!isset($this->inverseCatalog)) {
      $this->initializeCatalog();
    }

    $tokens = '';
    $chars = str_replace("\xe2\x86\x92", '->', $chars);

    for (
      $group_start = 0;
      $group_start < strlen($chars);
      $group_start = $group_end + 1
    ) {
      $newline_position = strpos($chars, "\n", $group_start);
      $slash_position = strpos($chars, "\\", $group_start);

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
