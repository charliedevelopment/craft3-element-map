<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap\services;

use Craft;
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

	/**
	 * Renders an element map relative to the given element.
	 * @param int $elementid The ID of the element to render the map relative to.
	 */
	public function render(int $elementid)
	{
		// Gather up necessary structure data to render the element map with.
		$results = $this->getElementMap($elementid);

		// Render the actual element map.
		return Craft::$app->view->renderTemplate('element-map/_map', ['map' => $results]);
	}

	/**
	 * Generates a map structure indicating elements that reference the given element, and elements that the given
	 * element references.
	 * @param int $elementid The ID of the element to generate a map for.
	 */
	private function getElementMap(int $elementid)
	{
		// Find incoming relationships to this element. Check for references to it, then trace those elements'
		// owners until we get to meaningful things, such as Category -in-> Matrix Block -in-> Entry.
		$fromdata = $this->getRelationshipGroups([$elementid], true);

		// Convert the retrieved element ids into data we use to display the map.
		$this->processRelationshipGroups($fromdata);

		// Find outgoing relationships from this element. This includes not only direct references, but any
		// child elements like matrix blocks must have their own external references included, this means we can
		// check things like Entry -contains-> Matrix Block -contains-> Asset.
		$toset_1 = [$elementid]; // Entry.
		$toset_2 = $this->getMatrixBlocksByOwners($toset_1); // Entry -> matrix blocks.
		$todata = $this->getRelationshipGroups(array_merge($toset_1, $toset_2), false);

		// Convert the retrieved element ids into data we use to display the map.
		$this->processRelationshipGroups($todata);

		return ['incoming' => $fromdata['results'], 'outgoing' => $todata['results']];
	}

	/**
	 * @param array $elementids The array of elements to get relationships for.
	 * @param bool $getsources Set to true when the elementids are for target elements, and the sources are being
	 * searched for, or false when the elementids are for source elements, and the targets are being looked for.
	 */
	private function getRelationshipGroups(array $elementids, bool $getsources)
	{
		if ($getsources) {
			$fromcol = 'targetId';
			$tocol = 'sourceId';
		} else {
			$fromcol = 'sourceId';
			$tocol = 'targetId';
		}

		// Get a list of elements where the given element IDs are part of the relationship,
		// either target or source, defined by `getsources`.
		$conditions = [
			'and',
			[
				'in',
				$fromcol,
				$elementids
			],
			[
				'or',
				'sourceSiteId is null',
				'sourceSiteId = :sourceSiteId',
			],
		];

		$params = [
			':sourceSiteId' => Craft::$app->getSites()->currentSite->id,
		];
		$results = (new Query())
			->select('r.' . $tocol . ' AS id, e.type AS type')
			->from('{{%relations}} r')
			->join('LEFT JOIN', '{{%elements}} e', 'r.' . $tocol . ' = e.id')
			->where($conditions, $params)
			->all();
		
		// Create element type groups in order to further process the element list.
		$elements = [
			'craft\elements\MatrixBlock' => [],
			'craft\elements\Entry' => [],
			'craft\elements\GlobalSet' => [],
			'craft\elements\Category' => [],
			'craft\elements\Tag' => [],
			'craft\elements\Asset' => [],
			'craft\elements\User' => [],
			'Other' => [],
			'results' => [],
		];
		$this->integrateGroupData($elements, $results);
		return $elements;
	}

	/**
	 * Processes input elements from a database query, and sorts them by type into an appropriate
	 * container for further processing.
	 * @param array &$groups A reference to the groups container to store elements within.
	 * @param array &elements The list of elements to store within the container.
	 */
	private function integrateGroupData(array &$groups, array &$elements) {
		foreach ($elements as $element) {
			if (isset($groups[$element['type']])) { // We know the type of element this is, store it.
				$groups[$element['type']][] = $element['id'];
			} else { // Some kind of element not handled by the map, store it in `Other`.
				$groups['Other'][] = $element['id'];
			}
		}
	}

	/**
	 * Retrieves a list of matrix block IDs based on the given set of owner ids.
	 * @param owners Retrieve all matrix blocks that are owned by any of the owners provided.
	 */
	private function getMatrixBlocksByOwners($owners) {
		$conditions = [
			'ownerId' => $owners,
			'ownerSiteId' => Craft::$app->getSites()->currentSite->id,
		];
		return (new Query())
			->select('id')
			->from('{{%matrixblocks}}')
			->where($conditions)
			->column();
	}

	/**
	 * Finds owner elements of matrix group items, and returns those elements.
	 * @param group A list of matrix block ids to find owners for.
	 */
	private function processMatrixGroup($group) {
		$conditions = [
			'mb.id' => $group,
		];
		return (new Query())
			->select('e.id AS id, e.type AS type')
			->from('{{%matrixblocks}} mb')
			->join('LEFT JOIN', '{{%elements}} e', 'mb.ownerId = e.id')
			->where($conditions)
			->all();
	}

	/**
	 * Converts entries into a list of standardized result items.
	 * @param group The IDs of the entries to convert.
	 */
	private function processEntryGroup($group) {
		$criteria = new EntryQuery('craft\elements\Entry');
		$criteria->id = $group;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'type' => 'entry',
				'title' => $element->title,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Converts global set items into a list of standardized result items.
	 * @param group The IDs of the global sets to convert.
	 */
	private function processGlobalSetGroup($group) {
		$criteria = new GlobalSetQuery('craft\elements\GlobalSet');
		$criteria->id = $group;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'type' => 'globalset',
				'title' => $element->name,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Converts categories into a list of standardized result items.
	 * @param group The IDs of the categories to convert.
	 */
	private function processCategoryGroup($group) {
		$criteria = new CategoryQuery('craft\elements\Category');
		$criteria->id = $group;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'type' => 'category',
				'title' => $element->title,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Converts tags into a list of standardized result items.
	 * @param group The IDs of the tags to convert.
	 */
	private function processTagGroup($group) {
		$criteria = new TagQuery('craft\elements\Tag');
		$criteria->id = $group;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'type' => 'tag',
				'title' => $element->title,
				'url' => '/' . Craft::$app->getConfig()->getGeneral()->cpTrigger . '/settings/tags/' . $element->groupId,
			];
		}
		return $results;
	}

	/**
	 * Converts assets into a list of standardized result items.
	 * @param group The IDs of the assets to convert.
	 */
	private function processAssetGroup($group) {
		$criteria = new AssetQuery('craft\elements\Asset');
		$criteria->id = $group;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'type' => 'asset',
				'title' => $element->title,
				'url' => $element->volume->hasUrls ? $element->getUrl() : UrlHelper::cpUrl('settings/assets/volumes/' . $element->volume->id),
			];
		}
		return $results;
	}

	/**
	 * Converts users into a list of standardized result items.
	 * @param group The IDs of the users to convert.
	 */
	private function processUserGroup($group) {
		$criteria = new UserQuery('craft\elements\User');
		$criteria->id = $group;
		$elements = $criteria->all();

		$results = [];
		foreach ($elements as $element) {
			$results[] = [
				'id' => $element->id,
				'type' => 'user',
				'title' => $element->name,
				'url' => $element->cpEditUrl,
			];
		}
		return $results;
	}

	/**
	 * Iterates over elements within each group, converting what it can find into result sets.
	 * @param groups A reference to the groups container that contains the processed and unprocessed elements.
	 */
	private function processRelationshipGroups(&$groups) {
		if (count($groups['craft\elements\MatrixBlock'])) {
			$data = $this->processMatrixGroup($groups['craft\elements\MatrixBlock']); // Process the data for this group.
			$groups['craft\elements\MatrixBlock'] = []; // Clear the data for this group.
			$this->integrateGroupData($groups, $data); // Re-integrate new data into the group container.
			$this->processRelationshipGroups($groups); // Process more groups.
		} else if (count($groups['craft\elements\Entry'])) {
			$data = $this->processEntryGroup($groups['craft\elements\Entry']); // Process the data for this group.
			$groups['craft\elements\Entry'] = []; // Clear the data for this group.
			$groups['results'] = array_merge($groups['results'], $data); // Add the results to the set.
			$this->processRelationshipGroups($groups); // Process more groups.
		} else if (count($groups['craft\elements\GlobalSet'])) {
			$data = $this->processGlobalSetGroup($groups['craft\elements\GlobalSet']); // Process the data for this group.
			$groups['craft\elements\GlobalSet'] = []; // Clear the data for this group.
			$groups['results'] = array_merge($groups['results'], $data); // Add the results to the set.
			$this->processRelationshipGroups($groups); // Process more groups.
		} else if (count($groups['craft\elements\Category'])) {
			$data = $this->processCategoryGroup($groups['craft\elements\Category']); // Process the data for this group.
			$groups['craft\elements\Category'] = []; // Clear the data for this group.
			$groups['results'] = array_merge($groups['results'], $data); // Add the results to the set.
			$this->processRelationshipGroups($groups); // Process more groups.
		} else if (count($groups['craft\elements\Tag'])) {
			$data = $this->processTagGroup($groups['craft\elements\Tag']); // Process the data for this group.
			$groups['craft\elements\Tag'] = []; // Clear the data for this group.
			$groups['results'] = array_merge($groups['results'], $data); // Add the results to the set.
			$this->processRelationshipGroups($groups); // Process more groups.
		} else if (count($groups['craft\elements\Asset'])) {
			$data = $this->processAssetGroup($groups['craft\elements\Asset']); // Process the data for this group.
			$groups['craft\elements\Asset'] = []; // Clear the data for this group.
			$groups['results'] = array_merge($groups['results'], $data); // Add the results to the set.
			$this->processRelationshipGroups($groups); // Process more groups.
		} else if (count($groups['craft\elements\User'])) {
			$data = $this->processUserGroup($groups['craft\elements\User']); // Process the data for this group.
			$groups['craft\elements\User'] = []; // Clear the data for this group.
			$groups['results'] = array_merge($groups['results'], $data); // Add the results to the set.
			$this->processRelationshipGroups($groups); // Process more groups.
		}
	}
}
