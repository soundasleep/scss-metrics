<?php

require(__DIR__ . "/vendor/autoload.php");

$parser = new Leafo\ScssPhp\Parser("test-source");
$result = $parser->parse(file_get_contents(__DIR__ . "/example.scss"));
// $result = $parser->parse(file_get_contents(__DIR__ . "/design-prototypes/site/css/_better.scss"));
// $result = $parser->parse(file_get_contents(__DIR__ . "/design-prototypes/site/css/_layout.scss"));
// $result = $parser->parse(file_get_contents(__DIR__ . "/vendor/leafo/scssphp/site/www/style/normalize.css"));
// print_r($result);

class Visitor {
  function enterDirective($name, $selectors) {
    echo "Directive[$name, $selectors]\n";
  }
  function leaveDirective() {

  }
  function enterBlock($selectors) {
    echo "Block[$selectors]\n";
  }
  function leaveBlock() {

  }
  function import($arg) {
    echo "Import[$arg]\n";
  }
  function assign($key, $value) {
    echo "Assign[$key = $value]\n";
  }
  function comment($comment) {
    echo "Comment[$comment]\n";
  }
  function include_($type, $args) {
    echo "Include[$type, " . implode(", ", $args) . "]\n";
  }
  function enterMixin($name, $args) {
    $printable = array();
    foreach ($args as $key => $value) {
      $printable[] = "$key=$value";
    }
    echo "Mixin[$name(" . implode(", ", $printable) . ")\n";
  }
  function leaveMixin() {

  }
}

function iterate_over($visitor, $result) {
  foreach ($result->children as $child) {
    switch ($child[0]) {
      case "block":
        $visitor->enterBlock(format_selectors($child[1]->selectors));
        iterate_over($visitor, $child[1]);
        $visitor->leaveBlock();
        break;

      case "import":
        $visitor->import(format_type($child[1]));
        break;

      case "directive":
        $visitor->enterDirective($child[1]->name, $child[1]->selectors);
        iterate_over($visitor, $child[1]);
        $visitor->leaveDirective();
        break;

      case "assign":
        $visitor->assign(format_type($child[1]), format_type($child[2]));
        break;

      case "comment":
        $visitor->comment($child[1]);
        break;

      case "include":
        $args = array();
        foreach ($child[2] as $c) {
          $args[] = format_type($c[1]);
        }
        $visitor->include_($child[1], $args);
        break;

      case "mixin":
        $args = array();
        foreach ($child[1]->args as $arg) {
          $args[$arg[0]] = format_type($arg[1]);
        }
        $visitor->enterMixin($child[1]->name, $args);
        iterate_over($visitor, $child[1]);
        $visitor->leaveMixin();
        break;

      default:
        throw new Exception("Unknown child type '" . $child[0] . "'");
    }
  }
}
iterate_over(new Visitor(), $result);

function format_type($var) {
  if ($var === null) {
    return null;
  }

  switch ($var[0]) {
    case "string":
      return implode(" ", $var[2]);
    case "number":
      return $var[1] . $var[2];
    case "list":
      $values = array();
      foreach ($var[2] as $child) {
        $values[] = format_type($child);
      }
      return implode(", ", $values);
    case "fncall":
      $values = array();
      foreach ($var[2] as $child) {
        $values[] = format_type($child[1]);
      }
      return $var[1] . "(" . implode(", ", $values) . ")";
    case "keyword":
      return $var[1];
    case "var":
      return "$" . $var[1];
    case "color":
      return sprintf("#%02x%02x%02x", $var[1], $var[2], $var[3]);
    case "unary":
      return $var[1] . format_type($var[2]);
    case "exp":
      return "(" . format_type($var[2]) . " " . $var[1] . " " . format_type($var[3]) . ")";
    default:
      throw new Exception("Unknown type '" . $var[0] . "': " . print_r($var, true));
  }
}

function format_selectors($array) {
  $top = array();
  foreach ($array as $top_selector) {
    foreach ($top_selector as $mid_selector) {
      $bottom = array();
      foreach ($mid_selector as $result) {
        if (is_array($result)) {
          $bottom[] = implode(";", $result);
        } else {
          $bottom[] = $result;
        }
      }
      $mid = implode("", $bottom);
    }
    $top[] = $mid;
  }
  return implode(", ", $top);
}