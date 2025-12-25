<?php

namespace Drupal\Tests\domain_config\Functional;

/**
 * Tests page caching results.
 *
 * @group domain_config
 */
class DomainConfigCacheTest extends DomainConfigTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain_access',
    'domain_config',
  ];

  /**
   * Tests that a domain response is proper.
   */
  public function testDomainResponse() {
    // No domains should exist.
    $this->domainTableIsEmpty();

    // Create a new domain programmatically.
    $this->domainCreateTestDomains(5);

    // Initialize expected with current cache entries.
    $database = \Drupal::database();
    $query = $database->query("SELECT cid FROM {cache_page}");
    $expected = $query->fetchCol();

    /** @var \Drupal\domain\DomainInterface[] $domains */
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    foreach ($domains as $domain) {
      $this->drupalGet($domain->getPath());
      // The page cache includes a colon at the end.
      $expected[] = $domain->getPath() . ':';
    }

    $database = \Drupal::database();
    $query = $database->query("SELECT cid FROM {cache_page}");
    $result = $query->fetchCol();

    $expected = array_unique($expected);
    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result, 'Cache returns as expected.');

    // Now create a node and test the cache.
    // Create an article node assigned to two domains.
    $ids = ['example_com', 'four_example_com'];
    $node1 = $this->drupalCreateNode([
      'type' => 'article',
      'field_domain_access' => [$ids],
      'path' => '/test',
    ]);

    $domain_by_cid = [];
    foreach ($domains as $domain) {
      $this->drupalGet($domain->getPath() . 'test');
      // The page cache includes a colon at the end.
      $cid = $domain->getPath() . 'test:';
      // Add the cache ID for the node.
      $expected[] = $cid;
      // Store the domain for later use.
      $domain_by_cid[$cid] = $domain;
    }

    $query = $database->query("SELECT cid FROM {cache_page}");
    $result = $query->fetchCol();

    sort($expected);
    sort($result);
    $this->assertEquals($expected, $result, 'Cache returns as expected.');

    // Verify that test page cache tags contain at most one domain related tag.
    $query = $database->query("SELECT cid, tags FROM {cache_page} WHERE cid LIKE :cid", [':cid' => '%test:']);
    $results = $query->fetchAll();

    // Loop over the test page cache entries and verify the tags.
    foreach ($results as $result) {
      $domain = $domain_by_cid[$result->cid];
      $tags = explode(' ', $result->tags);
      $domain_prefix = 'config:domain.config.' . $domain->id();
      // Verify that the expected cache tag is present for the domain.
      // Check the domain_config_test module for the related configuration.
      $expected_tag = NULL;
      switch ($domain->id()) {
        case 'one_example_com':
        case 'two_example_com':
        case 'three_example_com':
          $expected_tag = $domain_prefix . '.en.system.site';
          break;

        case 'four_example_com':
          $expected_tag = $domain_prefix . '.system.site';
          break;
      }
      // Verify that the expected cache tag is present.
      if ($expected_tag) {
        $this->assertContains($expected_tag, $tags, 'Cache tags contain the expected domain tag.');
      }
      // Verify that only the above domain tag is present and no others exist.
      // This will need to be updated if other custom domain config are added.
      $count_domain_tags = count(array_filter($tags, function ($tag) {
        return str_starts_with($tag, 'config:domain.config.');
      }));
      $this->assertLessThanOrEqual(1, $count_domain_tags, 'Cache tags contain at most one domain tag.');
    }
  }

}
