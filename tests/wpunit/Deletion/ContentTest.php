<?php
/**
 * What happens to a member's content. The addon calls wp_delete_user() with no user to
 * reassign to, so WordPress decides, post type by post type.
 */
class ContentTest extends AddonTestCase {

	public function test_their_posts_and_pages_go_to_the_trash() {
		$user_id = $this->login_as_member( 'author' );
		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$page_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'page',
			)
		);

		$this->submit();

		$this->assertSame( 'trash', get_post_status( $post_id ) );
		$this->assertSame( 'trash', get_post_status( $page_id ) );
	}

	public function test_their_drafts_go_to_the_trash_too() {
		$user_id = $this->login_as_member( 'author' );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'draft',
			)
		);

		$this->submit();

		$this->assertSame( 'trash', get_post_status( $post_id ) );
	}

	public function test_their_uploads_are_deleted_for_good() {
		$user_id       = $this->login_as_member( 'author' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'avatar.jpg',
				'post_author'    => $user_id,
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->submit();

		$this->assertNull( get_post( $attachment_id ) );
	}

	public function test_custom_post_types_follow_their_delete_with_user_setting() {
		register_post_type(
			'wpumda_keep',
			array(
				'supports'         => array( 'author' ),
				'delete_with_user' => false,
			)
		);
		register_post_type(
			'wpumda_remove',
			array(
				'supports'         => array( 'title' ),
				'delete_with_user' => true,
			)
		);
		register_post_type( 'wpumda_noauthor', array( 'supports' => array( 'title' ) ) );

		$user_id = $this->login_as_member( 'author' );
		$keep    = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'wpumda_keep',
			)
		);
		$remove  = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'wpumda_remove',
			)
		);
		$other   = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'wpumda_noauthor',
			)
		);

		$this->submit();

		$this->assertSame( 'publish', get_post_status( $keep ) );
		$this->assertNull( get_post( $remove ), 'Only posts and pages stop at the trash.' );
		$this->assertSame( 'publish', get_post_status( $other ) );

		unregister_post_type( 'wpumda_keep' );
		unregister_post_type( 'wpumda_remove' );
		unregister_post_type( 'wpumda_noauthor' );
	}

	public function test_other_members_content_is_left_alone() {
		$other_id = $this->create_member( 'author' );
		$post_id  = self::factory()->post->create( array( 'post_author' => $other_id ) );
		$this->login_as_member( 'author' );

		$this->submit();

		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertSame( (string) $other_id, get_post( $post_id )->post_author );
	}

	/**
	 * A post by someone else, for the member to comment on.
	 *
	 * @return int
	 */
	private function someone_elses_post() {
		return self::factory()->post->create( array( 'post_author' => $this->create_member( 'author' ) ) );
	}

	public function test_their_comments_lose_every_personal_detail() {
		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		// Primes the cache, so the test also shows it's cleared.
		get_comment( $comment_id );

		$this->submit();

		$this->assertCommentAnonymised( $comment_id );
	}

	public function test_their_comment_text_status_and_date_are_kept() {
		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment(
			$post_id,
			array(
				'user_id'          => $user_id,
				'comment_date'     => '2026-01-02 03:04:05',
				'comment_approved' => '1',
			)
		);

		$this->submit();

		$comment = get_comment( $comment_id );
		$this->assertSame( 'A comment worth keeping.', $comment->comment_content );
		$this->assertSame( '1', $comment->comment_approved );
		$this->assertSame( '2026-01-02 03:04:05', $comment->comment_date );
		$this->assertSame( (string) $post_id, $comment->comment_post_ID );
		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	/**
	 * The name is WordPress's own, so it's translated with WordPress's own translations.
	 */
	public function test_the_author_name_is_wordpresss_own() {
		add_filter(
			'gettext_default',
			function ( $translation, $text ) {
				return 'Anonymous' === $text ? 'Anonyme' : $translation;
			},
			10,
			2
		);

		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$this->submit();

		$this->assertSame( 'Anonyme', get_comment( $comment_id )->comment_author );
	}

	/**
	 * @dataProvider comment_statuses
	 *
	 * @param string $status
	 */
	public function test_comments_of_every_status_are_anonymised( $status ) {
		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment(
			$post_id,
			array(
				'user_id'          => $user_id,
				'comment_approved' => $status,
			)
		);

		$this->submit();

		$this->assertCommentAnonymised( $comment_id );
		$this->assertSame( $status, get_comment( $comment_id )->comment_approved );
	}

	public function comment_statuses() {
		return array(
			'approved' => array( '1' ),
			'held'     => array( '0' ),
			'spam'     => array( 'spam' ),
			'trash'    => array( 'trash' ),
		);
	}

	public function test_comments_on_their_own_trashed_posts_are_anonymised() {
		$user_id    = $this->login_as_member( 'author' );
		$post_id    = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$this->submit();

		$this->assertSame( 'trash', get_post_status( $post_id ) );
		$this->assertCommentAnonymised( $comment_id );
	}

	/**
	 * Left while logged out, so there's no user_id, only the member's email.
	 */
	public function test_comments_left_with_their_email_while_logged_out_are_anonymised() {
		$post_id    = $this->someone_elses_post();
		$this->login_as_member( 'subscriber', array( 'user_email' => 'leaving@example.com' ) );
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => 0 ) );

		$this->submit();

		$this->assertCommentAnonymised( $comment_id );
	}

	/**
	 * Left before they changed their email, so only the user_id connects it to them.
	 */
	public function test_comments_left_under_an_old_email_are_anonymised() {
		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member( 'subscriber', array( 'user_email' => 'new-address@example.com' ) );
		$comment_id = $this->leave_comment(
			$post_id,
			array(
				'user_id'              => $user_id,
				'comment_author_email' => 'old-address@example.com',
			)
		);

		$this->submit();

		$this->assertCommentAnonymised( $comment_id );
	}

	public function test_other_peoples_comments_are_left_alone() {
		$post_id = $this->someone_elses_post();
		$other   = $this->create_member();

		$members_comment = $this->leave_comment(
			$post_id,
			array(
				'user_id'              => $other,
				'comment_author'       => 'Staying Member',
				'comment_author_email' => 'staying@example.com',
			)
		);
		$guests_comment  = $this->leave_comment(
			$post_id,
			array(
				'comment_author'       => 'A Guest',
				'comment_author_email' => 'guest@example.com',
			)
		);

		$user_id = $this->login_as_member();
		$this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$this->submit();

		$this->assertSame( 'Staying Member', get_comment( $members_comment )->comment_author );
		$this->assertSame( 'staying@example.com', get_comment( $members_comment )->comment_author_email );
		$this->assertSame( (string) $other, get_comment( $members_comment )->user_id );
		$this->assertSame( 'A Guest', get_comment( $guests_comment )->comment_author );
		$this->assertSame( 'guest@example.com', get_comment( $guests_comment )->comment_author_email );
	}

	/**
	 * A member with no email must not match every guest comment left without one.
	 */
	public function test_a_member_with_no_email_leaves_guests_with_no_email_alone() {
		global $wpdb;

		$post_id    = $this->someone_elses_post();
		$guests     = $this->leave_comment(
			$post_id,
			array(
				'comment_author'       => 'A Guest',
				'comment_author_email' => '',
			)
		);
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$wpdb->update( $wpdb->users, array( 'user_email' => '' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$this->submit();

		$this->assertCommentAnonymised( $comment_id );
		$this->assertSame( 'A Guest', get_comment( $guests )->comment_author );
	}

	/**
	 * The filter WordPress's own personal data eraser honours, for plugins that must keep a
	 * comment as it is.
	 */
	public function test_wp_anonymize_comment_can_keep_a_comment() {
		$post_id = $this->someone_elses_post();
		$user_id = $this->login_as_member();
		$keep    = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );
		$erase   = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		add_filter(
			'wp_anonymize_comment',
			function ( $anonymize, $comment ) use ( $keep ) {
				return (int) $comment->comment_ID === $keep ? 'Kept for the accounts.' : $anonymize;
			},
			10,
			2
		);

		$this->submit();

		$this->assertSame( 'Leaving Member', get_comment( $keep )->comment_author );
		$this->assertCommentAnonymised( $erase );
	}

	public function test_comments_are_anonymised_by_the_time_the_after_hook_fires() {
		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$author = null;
		add_action(
			'wpum_after_delete_account',
			function () use ( $comment_id, &$author ) {
				$author = get_comment( $comment_id )->comment_author;
			}
		);

		$this->submit();

		$this->assertSame( 'Anonymous', $author );
	}

	public function test_a_refused_deletion_leaves_the_content_alone() {
		$user_id    = $this->login_as_member( 'author' );
		$post_id    = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		$this->submit( array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertSame( 'Leaving Member', get_comment( $comment_id )->comment_author );
		$this->assertSame( (string) $user_id, get_comment( $comment_id )->user_id );
	}

	public function test_a_deletion_refused_by_the_filter_leaves_comments_alone() {
		$post_id    = $this->someone_elses_post();
		$user_id    = $this->login_as_member();
		$comment_id = $this->leave_comment( $post_id, array( 'user_id' => $user_id ) );

		add_filter( 'wpum_before_delete_account_allowed', '__return_false' );

		$this->submit();

		$this->assertSame( 'Leaving Member', get_comment( $comment_id )->comment_author );
	}
}
