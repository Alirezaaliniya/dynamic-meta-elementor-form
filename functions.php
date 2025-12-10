<?php
/**
 * سیستم داینامیک ذخیره فیلدهای فرم المنتور به عنوان متا
 * این کد را در functions.php قالب خود قرار دهید
 */

add_action('elementor_pro/forms/new_record', 'handle_dynamic_meta_form_submission', 10, 2);

function handle_dynamic_meta_form_submission($record, $handler) {
    $form_name = $record->get_form_settings('form_name');
    
    // چک کردن اینکه آیا فرم با -meta تمام می‌شود
    if (!preg_match('/-meta$/', $form_name)) {
        return;
    }

    $raw_fields = $record->get('fields');
    $fields = [];
    
    // استخراج فیلدها
    foreach ($raw_fields as $id => $field) {
        $fields[$id] = $field['value'];
    }

    // پردازش هر فیلد
    foreach ($fields as $field_id => $field_value) {
        process_meta_field($field_id, $field_value, $fields);
    }
}

/**
 * پردازش فیلد بر اساس شناسه آن
 */
function process_meta_field($field_id, $field_value, $all_fields) {
    
    // 1. متای کاربر فعلی: -meta-user
    if (preg_match('/-meta-user$/', $field_id)) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $meta_key = str_replace('-meta-user', '', $field_id);
            update_user_meta($user_id, $meta_key, sanitize_text_field($field_value));
        }
        return;
    }

    // 2. متای محصول با ID مشخص: -meta-product-123
    if (preg_match('/-meta-product-(\d+)$/', $field_id, $matches)) {
        $product_id = intval($matches[1]);
        $meta_key = preg_replace('/-meta-product-\d+$/', '', $field_id);
        update_post_meta($product_id, $meta_key, sanitize_text_field($field_value));
        return;
    }

    // 3. متای محصول با ID داینامیک از فیلد دیگر: -meta-product
    if (preg_match('/-meta-product$/', $field_id)) {
        // جستجوی فیلد product_id یا product-id
        $product_id = find_related_id($all_fields, 'product');
        if ($product_id) {
            $meta_key = str_replace('-meta-product', '', $field_id);
            update_post_meta($product_id, $meta_key, sanitize_text_field($field_value));
        }
        return;
    }

    // 4. متای پست با ID مشخص: -meta-post-456
    if (preg_match('/-meta-post-(\d+)$/', $field_id, $matches)) {
        $post_id = intval($matches[1]);
        $meta_key = preg_replace('/-meta-post-\d+$/', '', $field_id);
        update_post_meta($post_id, $meta_key, sanitize_text_field($field_value));
        return;
    }

    // 5. متای پست با ID داینامیک: -meta-post
    if (preg_match('/-meta-post$/', $field_id)) {
        $post_id = find_related_id($all_fields, 'post');
        if ($post_id) {
            $meta_key = str_replace('-meta-post', '', $field_id);
            update_post_meta($post_id, $meta_key, sanitize_text_field($field_value));
        }
        return;
    }

    // 6. متای پست تایپ دلخواه با ID مشخص: -meta-{post_type}-123
    if (preg_match('/-meta-([a-zA-Z_]+)-(\d+)$/', $field_id, $matches)) {
        $post_type = $matches[1];
        $post_id = intval($matches[2]);
        
        // چک کردن وجود پست تایپ
        if (post_type_exists($post_type)) {
            $meta_key = preg_replace('/-meta-[a-zA-Z_]+-\d+$/', '', $field_id);
            update_post_meta($post_id, $meta_key, sanitize_text_field($field_value));
        }
        return;
    }

    // 7. متای پست تایپ دلخواه با ID داینامیک: -meta-{post_type}
    if (preg_match('/-meta-([a-zA-Z_]+)$/', $field_id, $matches)) {
        $post_type = $matches[1];
        
        // چک کردن وجود پست تایپ
        if (post_type_exists($post_type)) {
            $post_id = find_related_id($all_fields, $post_type);
            if ($post_id) {
                $meta_key = preg_replace('/-meta-[a-zA-Z_]+$/', '', $field_id);
                update_post_meta($post_id, $meta_key, sanitize_text_field($field_value));
            }
        }
        return;
    }

    // 8. متای ترم (دسته‌بندی/تگ): -meta-term-123
    if (preg_match('/-meta-term-(\d+)$/', $field_id, $matches)) {
        $term_id = intval($matches[1]);
        $meta_key = preg_replace('/-meta-term-\d+$/', '', $field_id);
        update_term_meta($term_id, $meta_key, sanitize_text_field($field_value));
        return;
    }

    // 9. متای ترم با ID داینامیک: -meta-term
    if (preg_match('/-meta-term$/', $field_id)) {
        $term_id = find_related_id($all_fields, 'term');
        if ($term_id) {
            $meta_key = str_replace('-meta-term', '', $field_id);
            update_term_meta($term_id, $meta_key, sanitize_text_field($field_value));
        }
        return;
    }

    // 10. متای کامنت: -meta-comment-789
    if (preg_match('/-meta-comment-(\d+)$/', $field_id, $matches)) {
        $comment_id = intval($matches[1]);
        $meta_key = preg_replace('/-meta-comment-\d+$/', '', $field_id);
        update_comment_meta($comment_id, $meta_key, sanitize_text_field($field_value));
        return;
    }
}

/**
 * پیدا کردن ID مرتبط از فیلدهای دیگر
 * مثلاً برای product، به دنبال فیلدی با نام product_id یا product-id می‌گردد
 */
function find_related_id($fields, $type) {
    $possible_keys = [
        $type . '_id',
        $type . '-id',
        $type . 'Id',
        $type . 'ID',
        'id-' . $type,
        'id_' . $type,
    ];

    foreach ($possible_keys as $key) {
        if (isset($fields[$key]) && !empty($fields[$key])) {
            return intval($fields[$key]);
        }
    }

    return null;
}

/**
 * هوک اضافی برای لاگ کردن (اختیاری - برای دیباگ)
 */
add_action('elementor_pro/forms/new_record', 'log_meta_form_submission', 11, 2);

function log_meta_form_submission($record, $handler) {
    $form_name = $record->get_form_settings('form_name');
    
    if (preg_match('/-meta$/', $form_name)) {
        // می‌توانید اینجا لاگ بگیرید یا اکشن دلخواه انجام دهید
        do_action('dynamic_meta_form_submitted', $record, $handler);
    }
}
