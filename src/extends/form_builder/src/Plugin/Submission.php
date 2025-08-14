<?php

namespace Simp\Core\extends\form_builder\src\Plugin;

use Simp\Core\modules\database\Database;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\current_user\CurrentUser;
use Simp\Core\modules\user\entity\User;
use Symfony\Component\HttpFoundation\Request;

class Submission
{
    protected array $fields;
    protected FormSettings $settings;
    protected array $raw_data;
    protected array $values;

    // Protection against direct access
    private int $sid;
    private string $form_name;
    private int $status;
    private string $created_at;
    private string $updated_at;
    private string $ip;
    private string $user_agent;
    private int $uid;
    private array $sids;

    public function __construct(int $sid = 0, string $form_name = '')
    {
        $this->raw_data = [];
        $this->values = [];
        $this->settings = new FormSettings('');
        $this->fields = [];
        $this->sid = 0;
        $this->form_name = $form_name;
        $this->status = 1;
        $this->created_at = '';
        $this->updated_at = '';
        $this->ip = '';
        $this->user_agent = '';
        $this->uid = 0;
        $this->sids = [];


        if (!empty($form_name)) {
            $this->settings = FormSettings::factory($form_name);
            $this->fields = FormConfigManager::factory()->getForm($form_name)['fields'] ?? [];
            $this->form_name = $form_name;

            $query = "SELECT sid FROM form_submissions WHERE webform = :webform ORDER BY sid DESC";
            $statement = Database::database()->con()->prepare($query);
            $statement->bindValue(':webform', $this->form_name);
            $statement->execute();
            $submission = $statement->fetchAll();
            if (!empty($submission)) {
                $this->sids = array_column($submission, 'sid');
            }
            $this->sid = $this->sid + 1;
            $this->sid = str_pad($this->sid, 10, '0', STR_PAD_LEFT);
        }

        if (!empty($sid)) {
            $query = "SELECT * FROM form_submissions WHERE sid = :sid";
            $statement = Database::database()->con()->prepare($query);
            $statement->bindValue(':sid', $sid);
            $statement->execute();
            $submission = $statement->fetch();

            if (!empty($submission)) {
                $this->sid = $submission['sid'];
                $this->form_name = $submission['webform'];
                $this->status = $submission['status'];
                $this->created_at = $submission['created'];
                $this->updated_at = $submission['updated'];
                $this->ip = $submission['ip'];
                $this->user_agent = $submission['user_agent'];
                $this->uid = $submission['uid'];

                $this->settings = FormSettings::factory($this->form_name);
                $this->fields = FormConfigManager::factory()->getForm($this->form_name)['fields'] ?? [];

                foreach ($this->fields as $key=>$field) {
                    $table = "forms__".$this->form_name."_".$field['name'];
                    $select = "SELECT * FROM {$table} WHERE sid = :sid";
                    $statement = Database::database()->con()->prepare($select);
                    $statement->bindValue(':sid', $this->sid);
                    $statement->execute();
                    $submission = $statement->fetchAll();
                    $this->values[$key] = $submission;
                }
            }
        }

    }

    public function create(array $submission_data): Submission
    {
        $request = Service::serviceManager()->request;
        $this->raw_data = $submission_data;
        $this->ip = $request->getClientIp();
        $this->user_agent = $request->headers->get('User-Agent');
        $this->uid = CurrentUser::currentUser()->getUser()->getUid();
        $this->status = $submission_data['status'] ?? 1;

        $query = "INSERT INTO form_submissions (webform, status, ip, user_agent, uid) VALUES (:webform, :status, :ip, :user_agent, :uid)";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':webform', $this->form_name);
        $statement->bindValue(':status', $this->status);
        $statement->bindValue(':ip', $this->ip);
        $statement->bindValue(':user_agent', $this->user_agent);
        $statement->bindValue(':uid', $this->uid);
        $statement->execute();
        $this->sid = Database::database()->con()->lastInsertId();

        if (!empty($this->sid)) {

            foreach ($this->fields as $key=>$field) {
                $table = "forms__".$this->form_name."_".$field['name'];

                if (!is_array($submission_data[$key])) {
                    $submission_data[$key] = [$submission_data[$key]];
                }

                foreach ($submission_data[$key] as $value) {
                    $insert_query = "INSERT INTO {$table} (sid, value) VALUES (:sid, :value)";
                    $insert_statement = Database::database()->con()->prepare($insert_query);
                    $insert_statement->bindValue(':sid', $this->sid);
                    $insert_statement->bindValue(':value', $value);
                    if ($insert_statement->execute()); {
                        $this->values[$key][] = [
                            'id' => Database::database()->con()->lastInsertId(),
                            'value' => $value,
                        ];
                    }
                }
            }
            $select = "SELECT created, updated FROM form_submissions WHERE sid = :sid";
            $statement = Database::database()->con()->prepare($select);
            $statement->bindValue(':sid', $this->sid);
            $statement->execute();
            $submission = $statement->fetch();
            $this->created_at = $submission['created'];
            $this->updated_at = $submission['updated'];
        }
        return $this;
    }

    public function delete(): bool
    {
        $query = "DELETE FROM form_submissions WHERE sid = :sid";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':sid', $this->sid);
        if ($statement->execute()) {
            foreach ($this->fields as $key=>$field) {
                $table = "forms__".$this->form_name."_".$field['name'];
                $delete_query = "DELETE FROM {$table} WHERE sid = :sid";
                $delete_statement = Database::database()->con()->prepare($delete_query);
                $delete_statement->bindValue(':sid', $this->sid);
                $delete_statement->execute();
            }
            return true;
        }
        return false;
    }

    public function update(array $submission_data): bool
    {
        $this->raw_data = $submission_data;
        $this->status = $submission_data['status'] ?? 1;
        $this->updated_at = date('Y-m-d H:i:s');
        $query = "UPDATE form_submissions SET status = :status, updated = :updated WHERE sid = :sid";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':status', $this->status);
        $statement->bindValue(':updated', $this->updated_at);
        $statement->bindValue(':sid', $this->sid);
        if ($statement->execute()) {
            foreach ($this->fields as $key=>$field) {
                $table = "forms__".$this->form_name."_".$field['name'];
                $delete_query = "DELETE FROM {$table} WHERE sid = :sid";
                $delete_statement = Database::database()->con()->prepare($delete_query);
                $delete_statement->bindValue(':sid', $this->sid);
                $delete_statement->execute();

                if (!is_array($submission_data[$key])) {
                    $submission_data[$key] = [$submission_data[$key]];
                }

                foreach ($submission_data[$key] as $value) {

                    if (!empty($value)) {
                        $insert_query = "INSERT INTO {$table} (sid, value) VALUES (:sid, :value)";
                        $insert_statement = Database::database()->con()->prepare($insert_query);
                        $insert_statement->bindValue(':sid', $this->sid);
                        $insert_statement->bindValue(':value', $value);
                        if ($insert_statement->execute()); {
                            $this->values[$key][] = [
                                'id' => Database::database()->con()->lastInsertId(),
                                'value' => $value,
                            ];
                        }
                    }

                }
            }
            return true;
        }
        return false;
    }

    public function getSubmissionData(): array
    {
        return $this->raw_data;
    }

    public function getAuthor()
    {
        return User::load($this->uid);
    }

    public function getSubmissionDataByField(string $field_name): array
    {
        $data = [];
        foreach ($this->values as $key=>$value) {
            if ($key == $field_name) {
                $data = $value;
            }
        }
        return $data;
    }

    public function getSubmissionDataByFieldId(int $field_id): array
    {
        $data = [];
        foreach ($this->values as $key=>$value) {
            foreach ($value as $v) {
                if ($v['id'] == $field_id) {
                    $data = $v;
                }
            }
        }
        return $data;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getSettings(): FormSettings
    {
        return $this->settings;
    }

    public function getRawData(): array
    {
        return $this->raw_data;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getSid(): int
    {
        return $this->sid;
    }

    public function getFormName(): string
    {
        return $this->form_name;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): string
    {
        return $this->updated_at;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): string
    {
        return $this->user_agent;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getSids(): array {
        return $this->sids;
    }

    public static function factory(int $sid = 0, string $form_name = ''): Submission
    {
        return new Submission($sid, $form_name);
    }

    public static function load(int $sid): Submission
    {
        return new Submission($sid);
    }

    public static function loadByFormName(string $form_name): Submission
    {
        return new Submission(0, $form_name);
    }

    public static function loadMultiple(array $sids): array
    {
        $submissions = [];
        foreach ($sids as $sid) {
            $submissions[] = new Submission($sid);
        }
        return $submissions;
    }

    public function __get(string $name)
    {
        if (array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        return match ($name) {
            'sid' => $this->sid,
            'form_name' => $this->form_name,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'uid' => $this->uid,
            'sids' => $this->sids,
            'webform' => $this->form_name,
            'submitted_by' => $this->getAuthor(),
            'submitted_on' => $this->created_at,
            'submitted_at' => $this->created_at,
            'updated_on' => $this->updated_at,
            'updated_at' => $this->updated_at,
            'submitted_ip' => $this->ip,
            'submitted_user_agent' => $this->user_agent,
            'submitted_uid' => $this->uid,
            'submitted_sids' => $this->sids,
            'submitted_fields' => $this->values,
            'submitted_data' => $this->raw_data,
            'submitted_settings' => $this->settings,
            'ip_address' => $this->ip,
            default => null,
        };
    }

    public function get(string $name)
    {
        return $this->__get($name);

    }

    public function toArray(): array
    {
        return [
            'sid' => $this->sid,
            'form_name' => $this->form_name,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            ...$this->values,
        ];
    }

}