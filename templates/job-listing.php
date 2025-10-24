<?php
/**
 * Template for job listing page
 * Variables available: $jobs, $departments, $locations, $search, $department, $location, $total_jobs, $page, $per_page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="gh-octagon-jobs" class="greenhouse wrapper d-flex align-items-center">
    <div class="col px-0">
        <div class="container">

            <!-- Search and Filters -->
            <form method="get" action="<?php echo esc_url(get_permalink()); ?>#gh-octagon-jobs">

                <!-- Keyword Search - Full Width Top -->
                <div class="row">
                    <div class="col-12">
                        <div id="field-keyword" class="form-group row">
                            <div class="field-label col-12">
                                <label for="gh_search"><h3>Keyword Search</h3></label>
                            </div>
                            <div class="field-field col-12 col-lg-8">
                                <div class="input-group">
                                    <input type="text"
                                           name="gh_search"
                                           id="gh_search"
                                           value="<?php echo esc_attr($search); ?>"
                                           class="form-control type__text" />
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text" style="border: none; cursor: pointer;">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Dropdowns -->
                <div class="row">
                    <?php if (count($boards_list) > 1): ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div id="field-board" class="form-group row">
                            <div class="field-label col-12">
                                <label for="gh_board"><h3>Select Board</h3></label>
                            </div>
                            <div class="field-field col-12">
                                <select name="gh_board"
                                        id="gh_board"
                                        class="form-control type__select">
                                    <option value="">All Boards</option>
                                    <?php foreach ($boards_list as $brd): ?>
                                        <option value="<?php echo esc_attr($brd->board_name); ?>"
                                                <?php selected($board, $brd->board_name); ?>>
                                            <?php echo esc_html($brd->board_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-12 col-md-6 col-lg-<?php echo count($boards_list) > 1 ? '3' : '4'; ?>">
                        <div id="field-country" class="form-group row">
                            <div class="field-label col-12">
                                <label for="gh_country"><h3>Select Country</h3></label>
                            </div>
                            <div class="field-field col-12">
                                <select name="gh_country"
                                        id="gh_country"
                                        class="form-control type__select">
                                    <option value="">All Countries</option>
                                    <?php foreach ($countries as $ctry): ?>
                                        <option value="<?php echo esc_attr($ctry->location_country); ?>"
                                                <?php selected($country, $ctry->location_country); ?>>
                                            <?php echo esc_html($ctry->location_country); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-<?php echo count($boards_list) > 1 ? '3' : '4'; ?>">
                        <div id="field-location" class="form-group row">
                            <div class="field-label col-12">
                                <label for="gh_location"><h3>Select Location</h3></label>
                            </div>
                            <div class="field-field col-12">
                                <select name="gh_location"
                                        id="gh_location"
                                        class="form-control type__select">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo esc_attr($loc->location_city); ?>"
                                                <?php selected($location, $loc->location_city); ?>>
                                            <?php echo esc_html($loc->location_city); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-<?php echo count($boards_list) > 1 ? '3' : '4'; ?>">
                        <div id="field-type" class="form-group row">
                            <div class="field-label col-12">
                                <label for="gh_employment_type"><h3>Employment Type</h3></label>
                            </div>
                            <div class="field-field col-12">
                                <select name="gh_employment_type"
                                        id="gh_employment_type"
                                        class="form-control type__select">
                                    <option value="">All Types</option>
                                    <?php foreach ($employment_types as $type): ?>
                                        <option value="<?php echo esc_attr($type->employment_type); ?>"
                                                <?php selected($employment_type, $type->employment_type); ?>>
                                            <?php echo esc_html($type->employment_type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Dropdown for Mobile -->
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-8">
                        <div id="field-department" class="form-group row d-md-none">
                            <div class="field-label col-12">
                                <label for="gh_department_mobile"><h3>Select Department</h3></label>
                            </div>
                            <div class="field-field col-12">
                                <select name="gh_department"
                                        id="gh_department_mobile"
                                        class="form-control type__select">
                                    <option value="">All Departments</option>
                                    <?php
                                    arsort($departments);
                                    foreach ($departments as $dept_name => $count):
                                    ?>
                                        <option value="<?php echo esc_attr($dept_name); ?>"
                                                <?php selected($department, $dept_name); ?>>
                                            <?php echo esc_html($dept_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

            </form>

            <!-- Content Area -->
            <div class="row mt-3">

                <!-- Left Sidebar - Departments (Desktop) -->
                <div class="col-12 col-md-6 d-none d-md-block">
                    <h2>Departments</h2>
                    <ul id="list-dept">
                        <li class="py-3 <?php echo empty($department) ? 'active' : ''; ?>"
                            data-department_id="">
                            <a href="<?php echo esc_url(remove_query_arg('gh_department')); ?>#gh-octagon-jobs">
                                All Departments <span>(<?php echo esc_html($total_jobs); ?>)</span>
                            </a>
                        </li>
                        <?php
                        arsort($departments);
                        foreach ($departments as $dept_name => $count):
                        ?>
                            <li class="py-3 <?php echo $department === $dept_name ? 'active' : ''; ?>"
                                data-department_id="<?php echo esc_attr($dept_name); ?>">
                                <a href="<?php echo esc_url(add_query_arg('gh_department', urlencode($dept_name))); ?>#gh-octagon-jobs">
                                    <?php echo esc_html($dept_name); ?> <span>(<?php echo esc_html($count); ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Right Side - Job Listings -->
                <div class="col-12 col-md-6">
                    <h2>Job Openings</h2>

                    <?php if (empty($jobs)): ?>
                        <div class="gh-no-jobs">
                            <p>No job openings found. Please try adjusting your search criteria.</p>
                        </div>
                    <?php else: ?>

                        <ul id="list-jobs">
                            <?php foreach ($jobs as $job):
                                $departments_arr = json_decode($job->departments, true);
                                $offices_arr = json_decode($job->offices, true);
                                $dept_name = !empty($departments_arr[0]['name']) ? $departments_arr[0]['name'] : '';
                                $office_name = !empty($offices_arr[0]['name']) ? $offices_arr[0]['name'] : '';
                            ?>
                                <li id="job-<?php echo esc_attr($job->gh_id); ?>"
                                    data-dept="<?php echo esc_attr($dept_name); ?>"
                                    data-board="<?php echo esc_attr($job->board_name); ?>"
                                    class="keyword-match">
                                    <a href="<?php echo esc_url(home_url('/job/' . $job->gh_id)); ?>"
                                       class="d-flex align-items-center py-3">
                                        <div class="col job-detail pl-0">
                                            <div class="title">
                                                <?php echo esc_html($job->title); ?>
                                                <?php if (count($boards_list) > 1): ?>
                                                    <span class="badge badge-secondary ml-2" style="font-size: 0.7em; vertical-align: middle;">
                                                        <?php echo esc_html($job->board_name); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="location">
                                                <?php echo esc_html($job->location); ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Pagination -->
                        <?php
                        $total_pages = ceil($total_jobs / $per_page);
                        if ($total_pages > 1):
                        ?>
                            <div class="gh-pagination mt-4">
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('jobpage', '%#%'),
                                    'format' => '',
                                    'current' => $page,
                                    'total' => $total_pages,
                                    'prev_text' => '&laquo; Previous',
                                    'next_text' => 'Next &raquo;',
                                    'type' => 'list'
                                ));
                                ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</div>
