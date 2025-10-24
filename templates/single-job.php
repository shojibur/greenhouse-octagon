<?php

/**
 * Template for single job page
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wpdb;
$job_id = get_query_var('gh_job_id');
$table_name = $wpdb->prefix . 'gh_octagon_jobs';

$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE gh_id = %d", $job_id));

if (!$job):
?>
    <div class="site-main">
        <article class="page-wrap">
            <div class="container-fluid container-max">
                <div class="row">
                    <div class="col col-12">
                        <h1 class="page-title job-page-title">Job Not Found</h1>
                        <div class="entry-content">
                            <p>Sorry, this job could not be found.</p>
                            <a href="<?php echo esc_url(home_url('/careers')); ?>" class="gh-btn gh-btn-primary">Back to All Jobs</a>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </div>
<?php
    get_footer();
    exit;
endif;

$departments = json_decode($job->departments, true);
$offices = json_decode($job->offices, true);
$metadata = json_decode($job->metadata, true);

// Set page title
add_filter('wp_title', function ($title) use ($job) {
    return $job->title . ' - ' . get_bloginfo('name');
}, 100);

?>

<div class="site-main greenhouse">
    <article id="job-<?php echo esc_attr($job->gh_id); ?>" class="page-wrap gh-single-job-article">
        <div class="container">

            <!-- Back to jobs link -->
            <div class="d-flex justify-content-end mb-3">
                <a href="#" class="btn btn-secondary" id="gh-back-btn">
                    View All Jobs
                </a>
            </div>

            <h2><?php echo esc_html($job->title); ?></h2>

            <div class="location mb-3 text-muted font-italic">
                <?php echo esc_html($job->location); ?>
            </div>

            <?php if ($job->requisition_id): ?>
                <div class="mb-3 small">
                    Requisition ID: <?php echo esc_html($job->requisition_id); ?>
                </div>
            <?php endif; ?>

            <!-- Job Content -->
            <div class="content">
                <?php echo wp_kses_post($job->content); ?>
            </div>

            <!-- Apply Now Button (Bottom) -->
            <button class="applyNowButton btn btn-primary mb-3">Apply Now</button>

            <!-- Cancel Button (Hidden initially) -->
            <button id="cancelButton" class="btn btn-secondary mb-3" style="display:none;">Cancel</button>

            <!-- Greenhouse Application Form -->
            <div id="grnhse_app" style="display:none; background-color: white; margin-top: 20px;"></div>

        </div><!-- .container -->
    </article><!-- .page-wrap -->
</div><!-- .site-main -->

<!-- Greenhouse Embed Script -->
<script src="https://boards.greenhouse.io/embed/job_board/js?for=octagon"></script>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var applyButton = document.querySelector('.applyNowButton');
        var cancelButton = document.getElementById('cancelButton');
        var iframeContainer = document.getElementById('grnhse_app');
        var backBtn = document.getElementById('gh-back-btn');

        // Back button functionality
        if (backBtn) {
            backBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (document.referrer && document.referrer.indexOf(window.location.hostname) !== -1) {
                    window.history.back();
                } else {
                    <?php
                    $careers_page = get_posts(array(
                        'post_type' => 'page',
                        'posts_per_page' => 1,
                        's' => '[gh_octagon_jobs]',
                        'fields' => 'ids'
                    ));
                    if (!empty($careers_page)) {
                        $careers_url = get_permalink($careers_page[0]);
                    } else {
                        $careers_url = home_url('/job/');
                    }
                    ?>
                    window.location.href = '<?php echo esc_url($careers_url); ?>';
                }
            });
        }

        // Apply Now button functionality
        if (applyButton) {
            applyButton.addEventListener('click', function() {
                // Load the Greenhouse iframe
                if (typeof Grnhse !== 'undefined') {
                    Grnhse.Iframe.load(<?php echo esc_js($job->gh_id); ?>);

                    // Show iframe
                    iframeContainer.style.display = 'block';

                    // Hide apply button and show cancel button
                    applyButton.style.display = 'none';
                    cancelButton.style.display = 'block';

                    // Scroll to iframe
                    setTimeout(function() {
                        iframeContainer.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 300);
                }
            });
        }

        // Cancel button functionality
        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                // Hide iframe and cancel button
                iframeContainer.style.display = 'none';
                cancelButton.style.display = 'none';

                // Show apply button again
                applyButton.style.display = 'block';
            });
        }

        // Auto-load the application form on page load
        // Wait for Greenhouse script to be fully loaded
        function loadApplicationForm() {
            if (typeof Grnhse !== 'undefined' && Grnhse.Iframe && Grnhse.Iframe.load) {
                // Load the Greenhouse iframe directly
                Grnhse.Iframe.load(<?php echo esc_js($job->gh_id); ?>);

                // Show iframe
                iframeContainer.style.display = 'block';

                // Hide apply button and show cancel button
                applyButton.style.display = 'none';
                cancelButton.style.display = 'block';
            } else {
                // Retry after a short delay
                setTimeout(loadApplicationForm, 100);
            }
        }

        // Start loading the form after a brief delay
        setTimeout(loadApplicationForm, 500);
    });
</script>

<?php
get_footer();
