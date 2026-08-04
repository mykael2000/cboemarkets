-- Email verification columns
ALTER TABLE users
  ADD COLUMN email_verified       tinyint(1)   NOT NULL DEFAULT 0  AFTER status,
  ADD COLUMN email_verify_code    varchar(10)  DEFAULT NULL,
  ADD COLUMN email_verify_token   varchar(64)  DEFAULT NULL,
  ADD COLUMN email_verify_expires datetime     DEFAULT NULL;

-- Mark all existing users as already verified so they are not locked out
UPDATE users SET email_verified = 1;
