<?php
class Controller_Entities_Records extends Api_Controller {
	
	public function action_index() {
		$con = $this->verifyConventionKey();
		$user = null;
		try {
			$user = $this->verifyAuthentication()->user;
		} catch (Api_Exception_Unauthorized $e) {
			if (!$con->isAuthorized()) {
				Logger::debug("No user, and no con authorization: trying to public retrieve");
				if ($this->tryHandlePublicRetrieve($con))
					return;
				throw $e;
			}
		}
		if (is_null($user) && $con->isAuthorized()) {// check if convention want to work on a per-user record
			if ($access_user = $this->request->query('user')) {
				try {
					$user = Model_User::byEmail($access_user);
				} catch (Model_Exception_NotFound $e) {
					throw new HTTP_Exception_404("User not found");
				}
				Logger::debug("Convention queries records for ".$user->email);
			}
		}
		switch ($this->request->method()) {
			case 'POST':
				return $this->create($con, $user, json_decode($this->request->body(), true));
			case 'PUT':
				return $this->update($con, $user, $this->request->param('id'), json_decode($this->request->body(), true));
			case 'DELETE':
				return $this->delete($con, $user, $this->request->param('id'));
			case 'GET':
				return $this->retrieve($con, $user, $this->request->param('id'));
		}
	}
	
	private function update(Model_Convention $con, Model_User $user, $id, $data) {
		$record = Model_User_Record::byDescriptor($con, $user, $id);
		$record->data = $data['data'];
		$record->content_type = $data['content_type'];
		if ($data['acl'] && Model_User_Record::isValidACL($data['acl']))
			$record->acl = $data['acl'];
		$record->save();
		$this->send(['status' => true]);
	}
	
	private function delete(Model_Convention $con, Model_User $user, $id) {
		Model_User_Record::byDescriptor($con, $user, $id)->delete();
		$this->send(['status' => true]);
	}
	
	private function create(Model_Convention $con, Model_User $user, $data) {
		$email_report = null;
		if (@$data['data'] and $data['content_type'] == 'application/json') {
			$user_record = json_decode($data['data'], true);
			if (@$user_record['post-save-email'])
				$email_report = $user_record['post-save-email'];
		}
		Model_User_Record::persist($con, $user, $data['descriptor'], $data['content_type'], $data['data'], $data['acl']);
		if ($email_report) {
			foreach (explode(",",$email_report) as $emailad) {
				Logger::info("Sending email notification to {$emailad}");
				$email = Twig::factory('user-record-notify');
				$email->descriptor = $data['descriptor'];
				$email->user = $user;
				
				try {
					$email->record = $user_record;
					Email::send("noreply@con-troll.org", $emailad, "Stored user record {$data['descriptor']}", $email->__toString(), [
							"Content-Type" => "text/html"
					]);
				} catch (Email_Exception $e) {
					Logger::error("Error sending email to $emailad: {$e}");
				}
			}
		}
		$this->send(['status' => true]);
	}

	private function retrieve(Model_Convention $con, Model_User $user = null, $id) {
		if (!$id) { // catalog request
			Logger::debug("Convention ".$con->pk()." retrieves identifier catalog");
			if ($con->isAuthorized() or $con->isManager($user)) {// convention or manager wants a catalog
				// return identifier catalog
				return $this->send(['data' => Model_User_Record::listDescriptors($con)]);
			} else
				throw new Api_Exception_Unauthorized($this, "Not authorized to list record identifiers");
		}
		
		if (($con->isAuthorized() or $con->isManager($user)) and $this->input()->fetch('list',FALSE)) {
			var_dump($this->input()->fetch('list',FALSE));
			Logger::debug("Getting catalog for {$id}, Convention {$con}");
			return $this->send(Model_User_Record::allByDescriptor($con, $id, $this->input()->fetch('all',FALSE)));
		}
		
		try {
			$this->send(['data' => Model_User_Record::byDescriptor($con, $user, $id)->as_array()]);
		} catch (Model_Exception_NotFound $e) {
			$this->send(['data'=> null]);
		}
	}
	
	private function tryHandlePublicRetrieve(Model_Convention $con) {
		$public_access_user = $this->request->query('user');
		if (!$public_access_user) {
			Logger::debug("Trying public access - no user");
			return false;
		}
		try {
			$public_access_user = Model_User::byEmail($public_access_user);
		} catch (Model_Exception_NotFound $e) {
			Logger::info("Trying public access - user $public_access_user not found");
			return false;
		}
		
		$id = $this->request->param('id');
		try {
			$record = Model_User_Record::byDescriptor($con, $public_access_user, $id);
			if ($record->isPublicReadable()) {
				$this->send(['data' => $record->as_array()]);
				return true;
			}
			Logger::debug("Trying public access, record not public: " . $record->acl);
		} catch (Model_Exception_NotFound $e) {
			Logger::info("Trying public access, no record $id found");
		}
		return false;
	}
}
