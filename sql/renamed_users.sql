BEGIN;

CREATE TABLE /*_*/renamed_users (
	user_old_name varbinary(255) NOT NULL,
	user_new_name varbinary(255) NOT NULL,
	mediate bool default 0,
	user_id INT UNSIGNED NULL
)/*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/renamed_users_primary ON /*_*/renamed_users (user_old_name, user_new_name);
CREATE INDEX /*i*/user_id ON /*_*/renamed_users(user_id);

COMMIT;
