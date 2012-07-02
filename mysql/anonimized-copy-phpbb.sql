TRUNCATE TABLE phpbb3_acl_groups;
TRUNCATE TABLE phpbb3_acl_options;
TRUNCATE TABLE phpbb3_acl_roles;
TRUNCATE TABLE phpbb3_acl_roles_data;
TRUNCATE TABLE phpbb3_acl_users;
TRUNCATE TABLE phpbb3_attachments;
TRUNCATE TABLE phpbb3_banlist;
TRUNCATE TABLE phpbb3_bbcodes;
TRUNCATE TABLE phpbb3_bookmarks;
TRUNCATE TABLE phpbb3_bots;
TRUNCATE TABLE phpbb3_config;
TRUNCATE TABLE phpbb3_confirm;
TRUNCATE TABLE phpbb3_disallow;
TRUNCATE TABLE phpbb3_drafts;
TRUNCATE TABLE phpbb3_extension_groups;
TRUNCATE TABLE phpbb3_extensions;
TRUNCATE TABLE phpbb3_forums;
TRUNCATE TABLE phpbb3_forums_access;
TRUNCATE TABLE phpbb3_forums_track;
TRUNCATE TABLE phpbb3_forums_watch;
TRUNCATE TABLE phpbb3_groups;
TRUNCATE TABLE phpbb3_icons;
TRUNCATE TABLE phpbb3_lang;
TRUNCATE TABLE phpbb3_log;
TRUNCATE TABLE phpbb3_moderator_cache;
TRUNCATE TABLE phpbb3_modules;
TRUNCATE TABLE phpbb3_poll_options;
TRUNCATE TABLE phpbb3_poll_votes;
TRUNCATE TABLE phpbb3_posts;
TRUNCATE TABLE phpbb3_privmsgs;
TRUNCATE TABLE phpbb3_privmsgs_folder;
TRUNCATE TABLE phpbb3_privmsgs_rules;
TRUNCATE TABLE phpbb3_privmsgs_to;
TRUNCATE TABLE phpbb3_profile_fields;
TRUNCATE TABLE phpbb3_profile_fields_data;
TRUNCATE TABLE phpbb3_profile_fields_lang;
TRUNCATE TABLE phpbb3_profile_lang;
TRUNCATE TABLE phpbb3_ranks;
TRUNCATE TABLE phpbb3_reports;
TRUNCATE TABLE phpbb3_reports_reasons;
TRUNCATE TABLE phpbb3_search_results;
TRUNCATE TABLE phpbb3_search_wordlist;
TRUNCATE TABLE phpbb3_search_wordmatch;
TRUNCATE TABLE phpbb3_sessions;
TRUNCATE TABLE phpbb3_sessions_keys;
TRUNCATE TABLE phpbb3_sitelist;
TRUNCATE TABLE phpbb3_smilies;
TRUNCATE TABLE phpbb3_styles;
TRUNCATE TABLE phpbb3_styles_imageset;
TRUNCATE TABLE phpbb3_styles_imageset_data;
TRUNCATE TABLE phpbb3_styles_template;
TRUNCATE TABLE phpbb3_styles_template_data;
TRUNCATE TABLE phpbb3_styles_theme;
TRUNCATE TABLE phpbb3_topics;
TRUNCATE TABLE phpbb3_topics_posted;
TRUNCATE TABLE phpbb3_topics_track;
TRUNCATE TABLE phpbb3_topics_watch;
TRUNCATE TABLE phpbb3_user_group;
TRUNCATE TABLE phpbb3_users;
TRUNCATE TABLE phpbb3_warnings;
TRUNCATE TABLE phpbb3_words;
TRUNCATE TABLE phpbb3_zebra;

INSERT INTO phpbb3_acl_groups SELECT * FROM spring.phpbb3_acl_groups;
INSERT INTO phpbb3_acl_options SELECT * FROM spring.phpbb3_acl_options;
INSERT INTO phpbb3_acl_roles SELECT * FROM spring.phpbb3_acl_roles;
INSERT INTO phpbb3_acl_roles_data SELECT * FROM spring.phpbb3_acl_roles_data;
INSERT INTO phpbb3_acl_users SELECT * FROM spring.phpbb3_acl_users;
INSERT INTO phpbb3_bbcodes SELECT * FROM spring.phpbb3_bbcodes;
INSERT INTO phpbb3_config SELECT * FROM spring.phpbb3_config;
INSERT INTO phpbb3_extensions SELECT * FROM spring.phpbb3_extensions;
INSERT INTO phpbb3_extension_groups SELECT * FROM spring.phpbb3_extension_groups;
INSERT INTO phpbb3_forums SELECT * FROM spring.phpbb3_forums;
INSERT INTO phpbb3_groups SELECT * FROM spring.phpbb3_groups;
INSERT INTO phpbb3_icons SELECT * FROM spring.phpbb3_icons;
INSERT INTO phpbb3_lang SELECT * FROM spring.phpbb3_lang;
INSERT INTO phpbb3_modules SELECT * FROM spring.phpbb3_modules;
INSERT INTO phpbb3_profile_fields SELECT * FROM spring.phpbb3_profile_fields;
INSERT INTO phpbb3_profile_lang SELECT * FROM spring.phpbb3_profile_lang;
INSERT INTO phpbb3_ranks SELECT * FROM spring.phpbb3_ranks;
INSERT INTO phpbb3_reports_reasons SELECT * FROM spring.phpbb3_reports_reasons;
INSERT INTO phpbb3_smilies SELECT * FROM spring.phpbb3_smilies;
INSERT INTO phpbb3_styles SELECT * FROM spring.phpbb3_styles;
INSERT INTO phpbb3_styles_imageset SELECT * FROM spring.phpbb3_styles_imageset;
INSERT INTO phpbb3_styles_imageset_data SELECT * FROM spring.phpbb3_styles_imageset_data;
INSERT INTO phpbb3_styles_template SELECT * FROM spring.phpbb3_styles_template;
INSERT INTO phpbb3_styles_template_data SELECT * FROM spring.phpbb3_styles_template_data;
INSERT INTO phpbb3_styles_theme SELECT * FROM spring.phpbb3_styles_theme;
INSERT INTO phpbb3_user_group SELECT * FROM spring.phpbb3_user_group;
INSERT INTO phpbb3_words SELECT * FROM spring.phpbb3_words;

INSERT INTO phpbb3_users (user_id, user_type, group_id, user_permissions, user_perm_from, user_regdate, username, username_clean, user_rank,  user_avatar, user_avatar_type, user_avatar_width, user_avatar_height)
SELECT user_id, user_type, group_id, user_permissions, user_perm_from, user_regdate, username, username_clean, user_rank, user_avatar, user_avatar_type, user_avatar_width, user_avatar_height
FROM spring.phpbb3_users WHERE user_new = 0;

-- username: Admin    password: Admin123 (change this or remove it once everything is working!)
INSERT INTO phpbb3_users (user_type, group_id, username, username_clean, user_regdate, user_password, user_email, user_lang, user_style, user_rank, user_colour, user_posts, user_permissions, user_ip, user_birthday, user_lastpage, user_last_confirm_key, user_post_sortby_type, user_post_sortby_dir, user_topic_sortby_type, user_topic_sortby_dir, user_avatar, user_sig, user_sig_bbcode_uid, user_from, user_icq, user_aim, user_yim, user_msnm, user_jabber, user_website, user_occ, user_interests, user_actkey, user_newpasswd) VALUES
	(3, 5, 'Admin', 'admin', 0, '$H$98YQ36026niEFIpBNs5ytBMAFTLou50', 'admin@example.com', 'en', 0, 1, 'AA0000', 0, '', '', '', '', '', 't', 'a', 't', 'd', '', '', '', '', '', '', '', '', '', '', '', '', '', '');

INSERT INTO phpbb3_user_group (group_id, user_id, user_pending, group_leader) VALUES
	(3569, (SELECT LAST_INSERT_ID()), 0, 0),
	(3571, (SELECT LAST_INSERT_ID()), 0, 0),
	(3572, (SELECT LAST_INSERT_ID()), 0, 0);

-- From attachments, only include those in 'Site content' forums.
INSERT INTO phpbb3_attachments
SELECT a.* FROM spring.phpbb3_attachments AS a, spring.phpbb3_topics AS t
WHERE t.forum_id IN (32,33,34,35,36,37) AND a.topic_id = t.topic_id
AND (extension = 'gif' or extension = 'jpg' or extension = 'jpeg' or extension = 'png' or extension = 'flv');

-- From topics, only include those in 'News' and 'Site content' forums.
INSERT INTO phpbb3_topics
SELECT * FROM spring.phpbb3_topics
WHERE forum_id IN (2,32,33,34,35,36,37);

-- Insert a random sample of other topics from forums directly below 'Spring', 'Content creation' and 'Development'.
/*INSERT INTO phpbb3_topics
SELECT * FROM spring.phpbb3_topics
WHERE forum_id IN (38,1,11,16,20,10, 14,13,9,47,52, 12,15,21,22,23)
ORDER BY rand() LIMIT 100;*/

-- From topics_posted, only include the records for the included topics.
INSERT INTO phpbb3_topics_posted
SELECT tp.* FROM spring.phpbb3_topics_posted AS tp, phpbb3_topics AS t
WHERE tp.topic_id = t.topic_id;

-- From posts, only include posts of the included topics.
INSERT INTO phpbb3_posts (post_id, topic_id, forum_id, poster_id, icon_id, post_time, post_approved, post_reported, enable_bbcode, enable_smilies, enable_magic_url, enable_sig, post_username, post_subject, post_text, post_checksum, post_attachment, bbcode_bitfield, bbcode_uid, post_postcount, post_edit_time, post_edit_reason, post_edit_user, post_edit_count, post_edit_locked)
SELECT post_id, p.topic_id, p.forum_id, poster_id, p.icon_id, post_time, post_approved, post_reported, enable_bbcode, enable_smilies, enable_magic_url, enable_sig, post_username, post_subject, post_text, post_checksum, post_attachment, bbcode_bitfield, bbcode_uid,	post_postcount, post_edit_time, post_edit_reason, post_edit_user, post_edit_count, post_edit_locked
FROM spring.phpbb3_posts AS p, phpbb3_topics AS t
WHERE p.topic_id = t.topic_id;

-- From poll_options, only include options for the included topics.
-- Note that poll_votes is private data, these may never be included!
INSERT INTO phpbb3_poll_options
SELECT p.* FROM spring.phpbb3_poll_options AS p, phpbb3_topics AS t
WHERE p.topic_id = t.topic_id;
