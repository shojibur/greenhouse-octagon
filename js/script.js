/**
 * Greenhouse Octagon Job Board JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Auto-submit filter form when dropdowns change
         */
        $('.greenhouse select.form-control').on('change', function() {
            $(this).closest('form').submit();
        });

        /**
         * Smooth scroll to results after form submit
         */
        if (window.location.hash === '#gh-octagon-jobs') {
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $('#gh-octagon-jobs').offset().top - 100
                }, 500);
            }, 100);
        }

        /**
         * Handle application form submission with AJAX
         */
        $('#gh-application-form-custom').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('.gh-btn-submit');
            var $message = $('#gh-form-message');
            var formData = new FormData(this);

            formData.append('action', 'gh_octagon_submit_application');
            formData.append('nonce', ghOctagon.nonce);

            // Disable submit button
            $submitBtn.prop('disabled', true).text('Submitting...');
            $message.hide();

            $.ajax({
                url: ghOctagon.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $message
                            .removeClass('error')
                            .addClass('success')
                            .html(response.data.message)
                            .fadeIn();

                        // Reset form
                        $form[0].reset();

                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: $message.offset().top - 100
                        }, 500);
                    } else {
                        $message
                            .removeClass('success')
                            .addClass('error')
                            .html(response.data.message)
                            .fadeIn();
                    }
                },
                error: function() {
                    $message
                        .removeClass('success')
                        .addClass('error')
                        .html('An error occurred. Please try again.')
                        .fadeIn();
                },
                complete: function() {
                    // Re-enable submit button
                    $submitBtn.prop('disabled', false).text('Submit Application');
                }
            });
        });

        /**
         * File input enhancement
         */
        $('input[type="file"]').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            var $label = $(this).siblings('small');

            if (fileName) {
                var fileSize = this.files[0].size / 1024 / 1024; // MB
                if (fileSize > 5) {
                    alert('File size must be less than 5MB');
                    $(this).val('');
                    return;
                }
                $label.text('Selected: ' + fileName);
            }
        });

        /**
         * Search form enhancement - prevent empty submissions
         */
        $('.greenhouse-office_filter').on('submit', function(e) {
            var $search = $(this).find('input[name="gh_search"]');
            if ($search.val().trim() === '') {
                $search.removeAttr('name');
            }
        });

        /**
         * Clear filters button
         */
        if ($('.gh-octagon-filters').length) {
            var hasFilters = window.location.search.includes('gh_search') ||
                           window.location.search.includes('gh_location') ||
                           window.location.search.includes('gh_department');

            if (hasFilters) {
                $('.gh-filter-submit').append(
                    '<a href="' + window.location.pathname + '#gh-octagon-jobs" class="gh-btn gh-btn-secondary" style="margin-left: 10px;">Clear Filters</a>'
                );
            }
        }

        /**
         * Department filter active state persistence
         */
        $('.gh-department-item').on('click', function() {
            var url = $(this).attr('href');
            if (url) {
                window.location.href = url;
            }
        });

        /**
         * Add loading state to job links
         */
        $('.gh-job-title a, .gh-job-actions a').on('click', function() {
            $(this).append(' <span class="loading">...</span>');
        });

        /**
         * Responsive menu toggle for departments (mobile)
         */
        if ($(window).width() <= 992) {
            $('.gh-octagon-sidebar h3').css('cursor', 'pointer').on('click', function() {
                $(this).next('.gh-department-list').slideToggle();
            });

            // Start collapsed on mobile
            $('.gh-department-list').hide();
            $('.gh-department-list .active').parent().show();
        }

        /**
         * Scroll to top button for long job lists
         */
        if ($('.gh-jobs-list').length) {
            $(window).on('scroll', function() {
                if ($(this).scrollTop() > 300) {
                    if (!$('#gh-scroll-top').length) {
                        $('body').append('<button id="gh-scroll-top" style="position:fixed;bottom:30px;right:30px;background:#c41230;color:white;border:none;padding:12px 16px;border-radius:4px;cursor:pointer;z-index:1000;display:none;">&uarr; Top</button>');
                        $('#gh-scroll-top').fadeIn();
                    } else {
                        $('#gh-scroll-top').fadeIn();
                    }
                } else {
                    $('#gh-scroll-top').fadeOut();
                }
            });

            $(document).on('click', '#gh-scroll-top', function() {
                $('html, body').animate({scrollTop: 0}, 500);
                return false;
            });
        }

        /**
         * Job listing animation on scroll
         */
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).css('opacity', '0').animate({opacity: 1}, 400);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });

            $('.gh-job-item').each(function() {
                observer.observe(this);
            });
        }

    });

})(jQuery);
