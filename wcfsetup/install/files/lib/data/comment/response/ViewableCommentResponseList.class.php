<?php
namespace wcf\data\comment\response;
use wcf\system\cache\runtime\UserProfileRuntimeCache;

/**
 * Represents a list of decorated comment response objects.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2016 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.comment.response
 * @category	Community Framework
 */
class ViewableCommentResponseList extends CommentResponseList {
	/**
	 * @inheritDoc
	 */
	public $decoratorClassName = ViewableCommentResponse::class;
	
	/**
	 * @inheritDoc
	 */
	public function readObjects() {
		parent::readObjects();
		
		if (!empty($this->objects)) {
			$userIDs = [];
			foreach ($this->objects as $response) {
				if ($response->userID) {
					$userIDs[] = $response->userID;
				}
			}
			
			if (!empty($userIDs)) {
				UserProfileRuntimeCache::getInstance()->cacheObjectIDs($userIDs);
			}
		}
	}
}
