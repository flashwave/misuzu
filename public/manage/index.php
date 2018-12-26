<?php
require_once '../../misuzu.php';

$generalPerms = perms_get_user(MSZ_PERMS_GENERAL, user_session_current('user_id', 0));

switch ($_GET['v'] ?? null) {
    default:
    case 'overview':
        echo tpl_render('manage.general.overview');
        break;

    case 'logs':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_VIEW_LOGS)) {
            echo render_error(403);
            break;
        }

        $logTake = 50;
        $logOffset = (int)($_GET['o'] ?? 0);
        $logCount = audit_log_count();
        $logs = audit_log_list($logOffset, $logTake);

        echo tpl_render('manage.general.logs', [
            'global_logs' => $logs,
            'global_logs_take' => $logTake,
            'global_logs_offset' => $logOffset,
            'global_logs_count' => $logCount,
            'global_logs_strings' => MSZ_AUDIT_LOG_STRINGS,
        ]);
        break;

    case 'quotes':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_VIEW_LOGS)) {
            echo render_error(403);
            break;
        }

        $setId = (int)($_GET['s'] ?? '');
        $quoteId = (int)($_GET['q'] ?? '');

        if (!empty($_POST['quote']) && csrf_verify('add_quote', $_POST['csrf'] ?? '')) {
            $quoteTime = strtotime($_POST['quote']['time'] ?? '');
            $parentId = empty($_POST['quote']['parent']) ? null : (int)($_POST['quote']['parent']);

            $quoteId = chat_quotes_add(
                $_POST['quote']['text'] ?? null,
                $_POST['quote']['user']['name'] ?? null,
                empty($_POST['quote']['user']['colour']) ? MSZ_COLOUR_INHERIT : (int)($_POST['quote']['user']['colour']),
                empty($_POST['quote']['user']['id']) ? null : (int)($_POST['quote']['user']['id']),
                empty($_POST['quote']['parent']) || $_POST['quote']['id'] == $parentId ? null : (int)($_POST['quote']['parent']),
                $quoteTime ? $quoteTime : null,
                empty($_POST['quote']['id']) ? null : (int)($_POST['quote']['id'])
            );

            header('Location: ?v=quotes' . ($setId ? '&s=' . $setId : '&q=' . $quoteId));
            break;
        }

        if ($quoteId) {
            tpl_vars([
                'current_quote' => chat_quotes_single($quoteId),
                'quote_parent' => $setId,
            ]);
        } elseif ($setId > 0) {
            tpl_var('quote_set', chat_quotes_set($setId));
        }

        $quoteCount = chat_quotes_count(true);
        $quotes = chat_quotes_parents($_GET['o'] ?? 0);

        echo tpl_render('manage.general.quotes', [
            'quote_count' => $quoteCount,
            'quote_offset' => (int)($_GET['o'] ?? 0),
            'quote_parents' => $quotes,
        ]);
        break;

    case 'emoticons':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_MANAGE_EMOTICONS)) {
            echo render_error(403);
            break;
        }

        echo tpl_render('manage.general.emoticons');
        break;

    case 'settings':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_MANAGE_SETTINGS)) {
            echo render_error(403);
            break;
        }

        echo tpl_render('manage.general.settings');
        break;

    case 'blacklist':
        if (!perms_check($generalPerms, MSZ_PERM_GENERAL_MANAGE_BLACKLIST)) {
            echo render_error(403);
            break;
        }

        $notices = [];

        if (!empty($_POST)) {
            if (!csrf_verify('ip_blacklist', $_POST['csrf'] ?? '')) {
                $notices[] = 'Verification failed.';
            } else {
                header(csrf_http_header('ip_blacklist'));

                if (!empty($_POST['blacklist']['remove']) && is_array($_POST['blacklist']['remove'])) {
                    foreach ($_POST['blacklist']['remove'] as $cidr) {
                        if (!ip_blacklist_remove($cidr)) {
                            $notices[] = sprintf('Failed to remove "%s" from the blacklist.', $cidr);
                        }
                    }
                }

                if (!empty($_POST['blacklist']['add']) && is_string($_POST['blacklist']['add'])) {
                    $cidrs = explode("\n", $_POST['blacklist']['add']);

                    foreach ($cidrs as $cidr) {
                        $cidr = trim($cidr);

                        if (empty($cidr)) {
                            continue;
                        }

                        if (!ip_blacklist_add($cidr)) {
                            $notices[] = sprintf('Failed to add "%s" to the blacklist.', $cidr);
                        }
                    }
                }
            }
        }

        echo tpl_render('manage.general.blacklist', [
            'notices' => $notices,
            'blacklist' => ip_blacklist_list(),
        ]);
        break;
}
