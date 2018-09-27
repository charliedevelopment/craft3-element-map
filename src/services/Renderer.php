<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap\services;

use Craft;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\db\Query;
use craft\elements\db\AssetQuery;
use craft\elements\db\CategoryQuery;
use craft\elements\db\EntryQuery;
use craft\elements\db\GlobalSetQuery;
use craft\elements\db\TagQuery;
use craft\elements\db\UserQuery;
use craft\helpers\UrlHelper;

use yii\base\Component;

class Renderer extends Component
{
	const ELEMENT_TYPE_MAP = [
		'craft\elements\Entry' => 'getEntryElements',
		'craft\elements\GlobalSet' => 'getGlobalSetElements',
		'craft\elements\Category' => 'getCategoryElements',
		'craft\elements\Tag' => 'getTagElements',
		'craft\elements\Asset' => 'getAssetElements',
		'craft\elements\User' => 'getUserElements',
		'craft\commerce\elements\Product' => 'getProductElements',
		'craft\commerce\elements\Variant' => 'getVariantElements',
	];

	/**
	 * @deprecated
	 */
	public function render(int $elementid, int $siteid)
	{
		Craft::$app->getDeprecator()->log(
			'charliedev\elementmap\services\Renderer::render()',
			'charliedev\elementmap\services\Renderer::render() is deprecated. Map results may be gathered individually with other functions such as getElementMap, getIncomingElements, and getOutgoingElements.'
		);

		// Gather up necessary structure data to render the element map with.
		$results = $this->getElementMap($elementid, $siteid);

		// Render the actual element map.
		return Craft::$app->view->renderTemplate(
			'element-map/_map',
			[
				'map' => $results
			]
		);
	}

	/**
	 * Generates a data structure containing elements that reference the given
	 * element and those that the given element references.
	 * @param int $elementId The ID of the element to retrieve map
	 * information about.
	 * @param int $siteId The ID of the site context that information should
	 * be gathered within.
	 */
	public function getElementMap($elementId, int $siteId)
	{
		if (!$elementId) { // No element, no element map.
			return null;
		}

		return [
			'incoming' => $this->getIncomingElements($elementId, $siteId),
			'outgoing' => $this->getOutgoingElements($elementId, $siteId),
		];
	}

	/**
	 * Retrieves a list of elements referencing the given element.
	 * @param int $elementId The ID of the element to retrieve map
	 * information about.
	 * @param int $siteId The ID of the site context that information should
	 * be gathered within.
	 */
	public function getIncomingElements($elementId, int $siteId)
	{
		if (!$elementId) { // No element, no related elements.
			return null;
		}

		// Assemble a set of elements that should be used as the targets.

		// Starting with the element itself.
		$targets = [$elementId];

		// Any variants within the element, as the variant and element share the
		// same editor pages (and can be referenced individually)
		$targets = array_merge($targets, $this->getVariantIdsByProducts($targets));

		// Find all elements that have any of these elements as targets.
		$relationships = $this->getRelationships($targets, $siteId, true);

		// Incoming connections may be coming from elements such as matrix
		// blocks. Before retrieving proper elements and generating the map,
		// their appropriate owner elements should be found.
		$relationships = $this->getUsableRelationElements($relationships, $siteId);

		// Retrieve the underlying elements from the relationships.
		return $this->getElementMapData($relationships, $siteId);
	}

	/**
	 * Retrieves a list of elements that the given element references.
	 * @param int $elementId The ID of the element to retrieve map
	 * information about.
	 * @param int $siteId The ID of the site context that information should
	 * be gathered within.
	 */
	public function getOutgoingElements($elementId, int $siteId)
	{
		if (!$elementId) { // No element, no related elements.
			return null;
		}

		// Assemble a set of elements that should be used as the sources.

		// Starting with the element itself.
		$sources = [$elementId];

		// Any variants within the element, as the variant and element share the
		// same editor pages.
		$sources = array_merge($sources, $this->getVariantIdsByProducts($sources));

		// Any matrix blocks, because they contain fields that may reference
		// other elements
		$sources = array_merge($sources, $this->getMatrixBlockIdsByOwners($sources));

		// Any super table blocks, for the same reason as matrix blocks, and
		// because they may themselves be contained within the matrix blocks.
		$sources = array_merge($sources, $this->getSuperTableBlockIdsByOwners($sources));

		// Any matrix blocks, again, in the case of any matrix blocks being
		// contained within the super table blocks. This is thankfully as
		// far as the recursion can go.
		$sources = array_merge($sources, $this->getMatrixBlockIdsByOwners($sources));

		// Find all elements that have any of these elements as sources.
		$relationships = $this->getRelationships($sources, $siteId, false);

		// Outgoing connections may be going to elements such as variants.
		// Before retrieving proper elements and generating the map, their
		// appropriate owner elements should be found.
		$relationships = $this->getUsableRelationElements($relationships, $siteId);

		// Retrieve the underlying elements from the relationships.
		return $this->getElementMapData($relationships, $siteId);
	}

	/**
	 * Attempts to retrieve variants for this element (used as a product), or
	 * returns nothing if the element is not a product or if Craft Commerce is
	 * not installed.
	 * @param $elementIds The element(s) to retrieve variants of.
	 * @return array An array of element IDs.
	 */
	private function getVariantIdsByProducts($elementIds)
	{
		// Make sure commerce is installed for this.
		if (!Craft::$app->getPlugins()->getPlugin('commerce')) {
			return [];
		}

		$conditions = [
			'productId' => $elementIds,
		];
		return (new Query())
			->select('id')
			->from('{{%commerce_variants}}')
			->where($conditions)
			->column();
	}

	/**
	 * Retrieves matrix blocks that are owned by the provided elements.
	 * @param $elementId The element(s) to retrieve blocks for.
	 * @return array An array of elements, with their ID `id` and element type
	 * `type`.
	 */
	private function getMatrixBlockIdsByOwners($elementIds)
	{
		$conditions = [
			'ownerId' => $elementIds,
		];
		return (new Query())
			->select('id')
			->from('{{%matrixblocks}}')
			->where($conditions)
			->column();
	}

	/**
	 * Retrieves super table blocks that are owned by the provided elements.
	 * @param $elementId The element(s) to retrieve blocks for.
	 * @return array An array of elements, with their ID `id` and element type
	 * `type`.
	 */
	private function getSuperTableBlockIdsByOwners($elementIds)
	{
		// Make sure super table is installed.
		if (!Craft::$app->getPlugins()->getPlugin('super-table')) {
			return [];
		}

		$conditions = [
			'ownerId' => $elementIds,
		];
		return (new Query())
			->select('id')
			->from('{{%supertableblocks}}')
			->where($conditions)
			->column();
	}

	/**
	 * Retrieves elements that are either the source or target of relationships
	 * with the provided elements.
	 * @param array $elementIds The array of elements to get relationships for.
	 * @param int $siteId The site ID that relationships should exist within.
	 * @param bool $getSources Set to true when the elementIds are for target
	 * elements, and the sources are being searched for, or false when the
	 * elementIds are for source elements, and the targets are being looked for.
	 * @return array An array of arrays, the outer array being keyed by element
	 * type, and the inner arrays containing element IDs.
	 */
	private function getRelationships(array $elementIds, int $siteId, bool $getSources)
	{
		if ($getSources) {
			$fromcol = 'targetId';
			$tocol = 'sourceId';
		} else {
			$fromcol = 'sourceId';
			$tocol = 'targetId';
		}

		// Get a list of elements where the given element IDs are part of the relationship,
		// either target or source, defined by `getSources`.
		$conditions = [
			'and',
			[
				'in',
				$fromcol,
				$elementIds
			],
			[
				'or',
				['sourceSiteId' => null],
				['sourceSiteId' => $siteId],
			],
		];

		$results = (new Query())
			->select('[[e.id]] AS id, [[e.type]] AS type')
			->from('{{%relations}} r')
			->leftJoin('{{%elements}} e', '[[r.' . $tocol . ']] = [[e.id]]')
			->where($conditions)
			->all();

		$results = $this->groupByType($results);

		return $results;
	}

	/**
	 * Finds elements within the relation set such as matrix blocks that should
	 * instead reference their owning elements.
	 * @param array $elements The elements to find usable elements for.
	 * @param int $siteId The site that the elements should be within.
	 * @return array An array of elements, with their ID `id` and element type
	 * `type`.
	 */
	private function getUsableRelationElements(array $elements, int $siteId)
	{
		$results = [];

		// This will iterate over available elements, bundled by type,
		// processing whole groups by type, either adding them to the result
		// set if they can be used outright, or retrieving a related element
		// to use to show the relationship instead.
		while (count($elements)) {
			// Retrieve the next element type.
			reset($elements);
			$type = key($elements);

			// Determine if that element type should be processed or if it
			// should simply be added to the result set.
			switch ($type) {
				/* Just in case individual variant mapping turns out to be a bad idea.
				// Variants should instead map to their products, as those are
				// the elements through which they may be edited.
				case 'craft\\commerce\\elements\\Variant':
					$items = $this->getProductsForVariants($elements[$type]);
					unset($elements[$type]);
					$items = $this->groupByType($items);
					$elements = $this->mergeGroups($elements, $items);
					break;
				*/
				// Matrix blocks should find their owners, and then those may
				// be reprocessed to determine if they are usable.
				case 'craft\\elements\\MatrixBlock':
					$items = $this->getOwnersForMatrixBlocks($elements[$type]);
					unset($elements[$type]);
					$items = $this->groupByType($items);
					$elements = $this->mergeGroups($elements, $items);
					break;
				// Super table blocks should find their owners, and then those
				// may be reprocessed to determine if they are usable.
				case 'verbb\\supertable\\elements\\SuperTableBlockElement':
					$items = $this->getOwnersForSuperTableBlocks($elements[$type]);
					unset($elements[$type]);
					$items = $this->groupByType($items);
					$elements = $this->mergeGroups($elements, $items);
					break;
				// Anything not processed above is alright to be added to the
				// result set and then retrieved later if it is supported.
				default:
					foreach ($elements[$type] as $element) {
						$results[] = [
							'id' => $element,
							'type' => $type,
						];
					}
					unset($elements[$type]);
					break;
			}
		}
		return $results;
	}

	/**
	 * Sorts the elements provided into individual arrays, keyed by type.
	 * @param array $elements Tge elements to group.
	 * @return array An array of arrays, the outer array being keyed by element
	 * type, and the inner arrays containing element IDs.
	 */
	private function groupByType(array $elements)
	{
		$results = [];
		foreach ($elements as $element) {
			if (!isset($results[$element['type']])) {
				$results[$element['type']] = [];
			}
			$results[$element['type']][] = $element['id'];
		}
		return $results;
	}

	/**
	 * Merges two groups in the same format as that provided by `groupByType`.
	 * @param array $groupsA The first group to merge.
	 * @param array $groupsB The second group to merge.
	 * @return array The two merged groups. An array of arrays, the outer array
	 * being keyed by element type, and the inner arrays containing element IDs.
	 */
	private function mergeGroups(array $groupsA, array $groupsB)
	{
		foreach ($groupsB as $type => $elements) {
			if (!isset($groupsA[$type])) {
				$groupsA[$type] = [];
			}
			$groupsA[$type] = array_merge($groupsA[$type], $elements);
		}
		return $groupsA;
	}

	/**
	 * Retrieves product IDs/types of product elements using the variant IDs
	 * provided.
	 * @param $elementIds An array of IDs.
	 * @return array An array of elements, with their ID `id` and element type
	 * `type`.
	 */
	private function getProductsForVariants(array $elementIds)
	{
		$conditions = [
			'productId' => $elementIds,
		];
		return (new Query())
			->select('id')
			->from('{{%commerce_variants}}')
			->where($conditions)
			->column();
	}

	/**
	 * Retrieves owner IDs/types of owning elements using the matrix block IDs
	 * provided.
	 * @param $elementIds An array of IDs.
	 * @return array An array of elements, with their ID `id` and element type
	 * `type`.
	 */
	private function getOwnersForMatrixBlocks($elementIds)
	{
		$conditions = [
			'mb.id' => $elementIds,
		];
		return (new Query())
			->select('[[e.id]] AS id, [[e.type]] AS type')
			->from('{{%matrixblocks}} mb')
			->leftJoin('{{%elements}} e', '[[mb.ownerId]] = [[e.id]]')
			->where($conditions)
			->all();
	}

	/**
	 * Retrieves owner IDs/types of owning elements using the super table block
	 * IDs provided.
	 * @param $elements An array of IDs.
	 * @return array An array of elements, with their ID `id` and element type
	 * `type`.
	 */
	private function getOwnersForSuperTableBlocks($elementIds)
	{
		$conditions = [
			'stb.id' => $elementIds,
		];
		return (new Query())
			->select('[[e.id]] AS id, [[e.type]] AS type')
			->from('{{%supertableblocks}} stb')
			->leftJoin('{{%elements}} e', '[[stb.ownerId]] = [[e.id]]')
			->where($conditions)
			->all();
	}

	/**
	 * Retrieves entry elements based on a set of IDs.
	 * @param $elementIds The IDs of the entries to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getEntryElements($elementIds, $siteId)
	{
		$criteria = new EntryQuery('craft\elements\Entry');
		$criteria->id = $elementIds;
		$criteria->siteId = $siteId;
		$criteria->status = null;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/cms/src/icons/newspaper.svg',
				'title' => $element->title,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Retrieves globalset elements based on a set of IDs.
	 * @param $elementIds The IDs of the globalsets to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getGlobalSetElements($elementIds, $siteId)
	{
		$criteria = new GlobalSetQuery('craft\elements\GlobalSet');
		$criteria->id = $elementIds;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/cms/src/icons/globe.svg',
				'title' => $element->name,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Retrieves category elements based on a set of IDs.
	 * @param $elementIds The IDs of the categories to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getCategoryElements($elementIds, $siteId)
	{
		$criteria = new CategoryQuery('craft\elements\Category');
		$criteria->id = $elementIds;
		$criteria->siteId = $siteId;
		$criteria->status = null;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/cms/src/icons/folder-open.svg',
				'title' => $element->title,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Retrieves tag elements based on a set of IDs.
	 * @param $elementIds The IDs of the tags to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getTagElements($elementIds, $siteId)
	{
		$criteria = new TagQuery('craft\elements\Tag');
		$criteria->id = $elementIds;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/cms/src/icons/tags.svg',
				'title' => $element->title,
				'url' => '/' . Craft::$app->getConfig()->getGeneral()->cpTrigger . '/settings/tags/' . $element->groupId,
			];
		}
		return $results;
	}

	/**
	 * Retrieves asset elements based on a set of IDs.
	 * @param $elementIds The IDs of the assets to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getAssetElements($elementIds, $siteId)
	{
		$criteria = new AssetQuery('craft\elements\Asset');
		$criteria->id = $elementIds;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/cms/src/icons/photo.svg',
				'title' => $element->title,
				'url' => $element->volume->hasUrls ? $element->getUrl() : UrlHelper::cpUrl('settings/assets/volumes/' . $element->volume->id),
			];
		}
		return $results;
	}

	/**
	 * Retrieves user elements based on a set of IDs.
	 * @param $elementIds The IDs of the users to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getUserElements($elementIds, $siteId)
	{
		$criteria = new UserQuery('craft\elements\User');
		$criteria->id = $elementIds;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/cms/src/icons/user.svg',
				'title' => $element->name,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Retrieves product elements based on a set of IDs.
	 * @param $elementIds The IDs of the products to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getProductElements($elementIds, $siteId)
	{
		$criteria = new ProductQuery('craft\commerce\elements\Product');
		$criteria->id = $elementIds;
		$criteria->siteId = $siteId;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/commerce/src/icon-mask.svg',
				'title' => $element->title,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Retrieves variant elements based on a set of IDs.
	 * @param $elementIds The IDs of the variants to retrieve.
	 * @param $siteId The ID of the site to use as the context for element data.
	 */
	private function getVariantElements($elementIds, $siteId)
	{
		$criteria = new VariantQuery('craft\commerce\elements\Variant');
		$criteria->id = $elementIds;
		$criteria->siteId = $siteId;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'icon' => '@vendor/craftcms/commerce/src/icon-mask.svg',
				'title' => $element->product->title . ': ' . $element->title,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Converts a set of elements to an array of map-ready associative arrays.
	 * @param array $elements An array of elements (with `id` and `type`) to
	 * retrieve map information for.
	 * @param int $siteId The ID of the site to retrieve element data within.
	 * @return array A set of elements that can be used to display the map.
	 */
	private function getElementMapData(array $elements, int $siteId)
	{
		$elements = $this->groupByType($elements);
		$results = [];

		while (count($elements)) {
			// Retrieve the next element type.
			reset($elements);
			$type = key($elements);

			if (isset(self::ELEMENT_TYPE_MAP[$type])) {
				$results = array_merge($results, call_user_func([$this, self::ELEMENT_TYPE_MAP[$type]], $elements[$type], $siteId));
			}

			unset($elements[$type]);
		}

		usort($results, function($a, $b) {
			return strcmp($a['title'], $b['title']);
		});

		return $results;
	}
}
