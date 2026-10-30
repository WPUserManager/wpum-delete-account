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
	 * Nothing happens to comments, so the member's name and email stay on the site after
	 * they've deleted their account. Pinned here as the current behaviour: see tests/COVERAGE.md.
	 */
	public function test_their_comments_stay_with_their_name_and_email() {
		$author_id = $this->create_member( 'author' );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		$user_id   = $this->login_as_member();

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'user_id'              => $user_id,
				'comment_author'       => 'Leaving Member',
				'comment_author_email' => 'leaving@example.com',
			)
		);

		$this->submit();

		$comment = get_comment( $comment_id );
		$this->assertSame( 'Leaving Member', $comment->comment_author );
		$this->assertSame( 'leaving@example.com', $comment->comment_author_email );
		$this->assertSame( (string) $user_id, $comment->user_id );
	}

	public function test_a_refused_deletion_leaves_the_content_alone() {
		$user_id = $this->login_as_member( 'author' );
		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

		$this->submit( array( 'password' => 'NotMyP@ssw0rd!' ) );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}
}
