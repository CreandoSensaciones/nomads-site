<?php

declare(strict_types=1);

namespace Drupal\Tests\cshs\Unit;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Tests\UnitTestCase;
use Drupal\cshs\Plugin\Field\FieldFormatter\CshsGroupByRootFormatter;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;

/**
 * Tests the `cshs_group_by_root` field formatter.
 *
 * @coversDefaultClass \Drupal\cshs\Plugin\Field\FieldFormatter\CshsGroupByRootFormatter
 * @group cshs
 */
class CshsGroupByRootFormatterUnitTest extends UnitTestCase {

  /**
   * Test the `viewElements()` logic.
   *
   * @param array $settings
   *   The subset of formatter settings.
   * @param string[] $tree
   *   The terms hierarchy. Each subarray represents the field delta.
   * @param array $expectations
   *   The expected tree after applying a formatter.
   *
   * @dataProvider providerViewElements
   * @dataProvider providerViewElementsLastChild
   */
  public function testViewElements(array $settings, array $tree, array $expectations): void {

    $mock = $this->getMockBuilder(CshsGroupByRootFormatter::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getSetting', 'getTermStorage', 'getEntitiesToView', 'getTranslationFromContext'])
      ->getMock();

    // Mock getSetting to return values based on $settings.
    $mock->method('getSetting')
      ->willReturnCallback(function ($name) use ($settings) {
            return $settings[$name] ?? NULL;
      });

    // Create terms with lineage.
    $terms = \array_map(function (array $lineage) {
      static $created = [];
      $terms = [];

      foreach ($lineage as $name) {
        if (!isset($created[$name])) {
            $link = $this->createMock(Link::class);
            $link->method('toString')->willReturn(static::getGeneratedLink($name));
            $created[$name] = $this->createMock(TermInterface::class);
            $created[$name]->method('id')->willReturn(\random_int(1, 10000));
            $created[$name]->method('label')->willReturn($name);
            $created[$name]->method('toLink')->willReturn($link);
        }

          $terms[] = $created[$name];
      }

      // Assign parents in reverse order.
      $reverse = \array_reverse($lineage);
      foreach ($reverse as $name) {
        $parent_item = $this->createMock(EntityReferenceFieldItemListInterface::class);
        $parent_item->target_id = ($parent = \next($reverse)) ? $created[$parent]->id() : NULL;
        $created[$name]->parent = $parent_item;
      }

      return $terms;
    }, $tree);

    // Set terms to view.
    $terms_to_view = \array_map(static fn (array $lineage) => \end($lineage), $terms);
    $mock->method('getEntitiesToView')->willReturn($terms_to_view);

    // Create term storage mock and use callback for loadAllParents.
    $term_storage = $this->createMock(TermStorageInterface::class);
    $term_storage->method('loadAllParents')
      ->willReturnCallback(function ($term_id) use ($terms_to_view, $terms) {
        foreach ($terms_to_view as $index => $term) {
          if ($term->id() === $term_id) {
            return array_reverse($terms[$index]);
          }
        }
        return [];
      });

    $mock->method('getTermStorage')->willReturn($term_storage);
    $mock->method('getTranslationFromContext')->willReturnArgument(0);

    $elements = $mock->viewElements(
      $this->createMock(EntityReferenceFieldItemListInterface::class),
      LanguageInterface::LANGCODE_DEFAULT
    );

    static::assertCount(\count($expectations), $elements);

    foreach ($expectations as $children) {
      $group_title = (string) \array_shift($children);
      $group = \current($elements);
      static::assertSame((string) $group['#title'], $group_title, $group_title);
      static::assertSame(
        \array_map('strval', $children),
        \array_map('strval', \array_column($group['#terms'], 'label')),
        $group_title
      );
      \next($elements);
    }
  }

  /**
   * Returns the test suites.
   *
   * @return \Generator
   *   The test suites.
   */
  public static function providerViewElements(): \Generator {
    $tree = [
      ['a', 'b', 'c'],
      ['a', 'b1'],
      ['a', 'b', 'c', 'd'],
      ['a1', 'b1', 'c1'],
      ['a1', 'b1', 'c1', 'd1'],
    ];

    yield [
      [
        'depth' => 0,
        'linked' => TRUE,
        'reverse' => FALSE,
        'sort' => CshsGroupByRootFormatter::SORT_NONE,
        'last_child' => FALSE,
      ],
      $tree,
      [
        [
          static::getGeneratedLink('a'),
          static::getGeneratedLink('b'),
          static::getGeneratedLink('c'),
          static::getGeneratedLink('b1'),
          static::getGeneratedLink('d'),
        ],
        [
          static::getGeneratedLink('a1'),
          static::getGeneratedLink('b1'),
          static::getGeneratedLink('c1'),
          static::getGeneratedLink('d1'),
        ],
      ],
    ];

    yield [
      [
        'depth' => 0,
        'linked' => FALSE,
        'reverse' => FALSE,
        'sort' => CshsGroupByRootFormatter::SORT_ASC,
        'last_child' => FALSE,
      ],
      $tree,
      [
        ['a', 'b', 'b1', 'c', 'd'],
        ['a1', 'b1', 'c1', 'd1'],
      ],
    ];

    yield [
      [
        'depth' => 0,
        'linked' => FALSE,
        'reverse' => FALSE,
        'sort' => CshsGroupByRootFormatter::SORT_DESC,
        'last_child' => FALSE,
      ],
      $tree,
      [
        ['a', 'd', 'c', 'b1', 'b'],
        ['a1', 'd1', 'c1', 'b1'],
      ],
    ];

    yield [
      [
        'depth' => 0,
        'linked' => TRUE,
        'reverse' => FALSE,
        'sort' => CshsGroupByRootFormatter::SORT_DESC,
        'last_child' => FALSE,
      ],
      $tree,
      [
        [
          static::getGeneratedLink('a'),
          static::getGeneratedLink('d'),
          static::getGeneratedLink('c'),
          static::getGeneratedLink('b1'),
          static::getGeneratedLink('b'),
        ],
        [
          static::getGeneratedLink('a1'),
          static::getGeneratedLink('d1'),
          static::getGeneratedLink('c1'),
          static::getGeneratedLink('b1'),
        ],
      ],
    ];

    yield [
      [
        'depth' => 0,
        'linked' => FALSE,
        'reverse' => TRUE,
        'sort' => CshsGroupByRootFormatter::SORT_NONE,
        'last_child' => FALSE,
      ],
      $tree,
      [
        ['c', 'b', 'a'],
        ['b1', 'a'],
        ['d', 'c', 'b', 'a'],
        ['c1', 'b1', 'a1'],
        ['d1', 'c1', 'b1', 'a1'],
      ],
    ];

    yield [
      [
        'depth' => 2,
        'linked' => FALSE,
        'reverse' => FALSE,
        'sort' => CshsGroupByRootFormatter::SORT_NONE,
        'last_child' => FALSE,
      ],
      $tree,
      [
        // Here we have 2 items because the hierarchy at each delta differs.
        ['a', 'b', 'b1'],
        ['a1', 'b1'],
      ],
    ];

    yield [
      [
        'depth' => 1,
        'linked' => FALSE,
        'reverse' => FALSE,
        'sort' => CshsGroupByRootFormatter::SORT_ASC,
        'last_child' => FALSE,
      ],
      $tree,
      [
        ['a'],
        ['a1'],
      ],
    ];
  }

  /**
   * Returns the test suites.
   *
   * @return \Generator
   *   The test suites.
   */
  public static function providerViewElementsLastChild(): \Generator {
    foreach (self::providerViewElements() as [$settings, $tree, $expectations]) {
      $settings['last_child'] = TRUE;

      foreach ($expectations as $i => $list) {
        $first_value = \reset($list);
        $first_key = \key($list);
        $last_value = \end($list);
        $last_key = \key($list);

        $expectations[$i] = [
          // Group title.
          $first_key => $first_value,
          // Deepest child.
          $last_key => $last_value,
        ];
      }

      yield [$settings, $tree, $expectations];
    }
  }

  /**
   * Returns the mocked link to the term page.
   *
   * @param string $name
   *   The term name.
   *
   * @return \Drupal\Core\GeneratedLink
   *   The mocked link.
   */
  protected static function getGeneratedLink(string $name): GeneratedLink {
    return (new GeneratedLink())->setGeneratedLink(\sprintf('<a href="/taxonomy/term/%1$s">%s</a>', $name));
  }

}
