<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Api
{
    public function hooks(): void
    {
        add_action('rest_api_init', [$this,'routes']);
    }
    private function route(string $path, string $methods, callable $fn, string $cap = 'gmpp_edit'): void
    {
        register_rest_route('gmpp/v1', $path, ['methods' => $methods,'permission_callback' => static fn()=>current_user_can($cap),'callback' => static function ($r) use ($fn) {
            try {
                return rest_ensure_response($fn($r));
            } catch (\DomainException $e) {
                return new \WP_Error('gmpp_forbidden', $e->getMessage(), ['status' => 403]);
            } catch (\OutOfBoundsException $e) {
                return new \WP_Error('gmpp_missing', $e->getMessage(), ['status' => 404]);
            } catch (\InvalidArgumentException $e) {
                $field = '';
                foreach (Admin::labels() as $key => $label) {
                    if (str_starts_with($e->getMessage(), $label . ':')) {
                        $field = $key;
                        break;
                    }
                }
                if (!$field && str_contains($e->getMessage(), 'nome del prodotto')) {
                    $field = 'name';
                }
                return new \WP_Error('gmpp_invalid', $e->getMessage(), ['status' => 422,'field' => $field]);
            } catch (\Throwable $e) {
                return new \WP_Error('gmpp_conflict', $e->getMessage(), ['status' => 409]);
            }
        }]);
    }
    private static function access(int $id, string $cap = 'gmpp_edit'): array
    {
        $row = Passport::get($id);
        if (!Store::can($row, $cap)) {
            throw new \DomainException('Passaporto non accessibile.');
        } return $row;
    }
    public function routes(): void
    {
        $this->route('/passports', 'GET', static function ($r) {
            global $wpdb;
            $page = max(1, (int)$r['page']);
            $limit = max(1, min(100, (int)($r['per_page'] ?: 20)));
            $where = '1=1';
            $args = [];
            if (!current_user_can('gmpp_edit_others')) {
                $where .= ' AND owner=%d';
                $args[] = get_current_user_id();
            }
            if ($r['search']) {
                $where .= ' AND (title LIKE %s OR sku LIKE %s)';
                $term = '%' . $wpdb->esc_like(sanitize_text_field($r['search'])) . '%';
                $args[] = $term;
                $args[] = $term;
            }
            if (in_array($r['state'], ['draft','published','archived'], true)) {
                $where .= ' AND state=%s';
                $args[] = $r['state'];
            }
            $table = Store::table('passports');
            $sort = in_array($r['sort'], ['title','id','updated_at','sku'], true) ? $r['sort'] : 'id';
            $dir = $r['direction'] === 'asc' ? 'ASC' : 'DESC';
            $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE $where", $table, ...$args)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE contains fixed fragments only; all filter values use placeholders.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT id,uuid,title,sku,kind,parent_id,state,lock_version,published_revision,updated_at,wc_id FROM %i WHERE $where ORDER BY %i $dir LIMIT %d OFFSET %d", ...array_merge([$table], $args, [$sort,$limit,($page - 1) * $limit])), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed WHERE fragments, bound values and identifiers; direction is ASC or DESC only.
            return ['rows' => $rows,'total' => $total,'page' => $page,'pages' => (int)ceil($total / $limit)];
        });
        $this->route('/choices', 'GET', static function ($r) {
            global $wpdb;
            $where = '1=1';
            $args = [];
            if (!current_user_can('gmpp_edit_others')) {
                $where .= ' AND owner=%d';
                $args[] = get_current_user_id();
            }
            $kinds = ['variant' => ['model'],'batch' => ['model','variant'],'item' => ['model','variant','batch']];
            if (isset($kinds[$r['kind']])) {
                $where .= ' AND kind IN (' . implode(',', array_fill(0, count($kinds[$r['kind']]), '%s')) . ')';
                $args = array_merge($args, $kinds[$r['kind']]);
            }
            if ($r['search']) {
                $term = '%' . $wpdb->esc_like(sanitize_text_field($r['search'])) . '%';
                $where .= ' AND (title LIKE %s OR sku LIKE %s)';
                $args[] = $term;
                $args[] = $term;
            }
            return $wpdb->get_results($wpdb->prepare("SELECT id,title,sku,kind FROM %i WHERE $where ORDER BY title,id LIMIT 50", Store::table('passports'), ...$args), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE contains fixed fragments and generated placeholders; each value and identifier is bound.
        });
        $this->route('/passports', 'POST', static function ($r) {
            $input = (array)$r->get_json_params();
            unset($input['wc_id']);
            return Passport::create($input);
        });
        $this->route('/passports/(?P<id>\d+)', 'GET', static fn($r)=>self::access((int)$r['id']));
        $this->route('/passports/(?P<id>\d+)', 'PUT', static fn($r)=>Passport::save((int)$r['id'], (array)$r->get_json_params(), (int)$r['lock_version']));
        $this->route('/passports/(?P<id>\d+)/publish', 'POST', static fn($r)=>Passport::publish((int)$r['id'], (int)$r['lock_version'], (string)$r['reason']), 'gmpp_publish');
        $this->route('/passports/(?P<id>\d+)/archive', 'POST', static fn($r)=>Passport::archive((int)$r['id'], (int)$r['lock_version']), 'gmpp_publish');
        $this->route('/passports/(?P<id>\d+)/duplicate', 'POST', static function ($r) {
            $row = self::access((int)$r['id']);
            $data = $row['data'];
            $data['name'] = ($data['name'] ?? 'Prodotto') . ' — copia';
            unset($data['sku'], $data['serial'], $data['batch'], $data['external_id']);
            return Passport::create(['data' => $data,'kind' => $row['kind'],'parent_id' => $row['parent_id']]);
        });
        $this->route('/passports/(?P<id>\d+)/history', 'GET', static function ($r) {
            self::access((int)$r['id']);
            global $wpdb;
            return ['revisions' => $wpdb->get_results($wpdb->prepare('SELECT id,digest,reason,created_at FROM %i WHERE passport_id=%d ORDER BY id DESC', Store::table('revisions'), (int)$r['id']), ARRAY_A),'events' => $wpdb->get_results($wpdb->prepare('SELECT action,detail,created_at FROM %i WHERE passport_id=%d ORDER BY id DESC LIMIT 100', Store::table('events'), (int)$r['id']), ARRAY_A)];
        });
        $this->route('/passports/(?P<id>\d+)/restore', 'POST', static function ($r) {
            self::access((int)$r['id'], 'gmpp_review');
            return Passport::restore((int)$r['id'], (int)$r['revision'], (int)$r['lock_version']);
        }, 'gmpp_review');
        $this->route('/passports/(?P<id>\d+)/serials', 'POST', static function ($r) {
            self::access((int)$r['id']);
            $serials = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array)$r['serials']))));
            if (!$serials || count($serials) > 10000) {
                throw new \InvalidArgumentException('Inserisci da 1 a 10.000 seriali univoci.');
            }
            return Jobs::create('serials', ['parent_id' => (int)$r['id'],'rows' => array_map(static fn($s)=>['serial' => $s], $serials)], count($serials));
        });
        $this->route('/bulk', 'POST', static function ($r) {
            $rows = [];
            $action = (string)$r['action'];
            if (!in_array($action, ['publish','archive','review'], true)) {
                throw new \InvalidArgumentException('Azione non valida.');
            }
            foreach (array_slice((array)$r['rows'], 0, 10000) as $item) {
                $row = self::access((int)$item['id'], $action === 'review' ? 'gmpp_review' : 'gmpp_publish');
                $rows[] = ['id' => (int)$row['id'],'version' => (int)$item['version']];
            }
            if (!$rows) {
                throw new \InvalidArgumentException('Seleziona almeno una scheda.');
            }
            return Jobs::create('bulk', ['rows' => $rows,'action' => $action,'reason' => sanitize_text_field($r['reason'] ?: 'Aggiornamento massivo')], count($rows));
        });
        $this->route('/settings', 'GET', static fn()=>['settings' => Store::settings(),'connections' => Connections::status(),'profiles' => Profiles::all(),'clone' => Store::is_clone(),'woo' => function_exists('wc_get_product')], 'gmpp_manage');
        $this->route('/profiles/(?P<profile>generic|furniture|textile)', 'PUT', static fn($r)=>Profiles::save($r['profile'], (array)$r->get_json_params()), 'gmpp_manage');
        $this->route('/settings', 'PUT', static function ($r) {
            $old = Store::settings();
            $s = (array)$r->get_json_params();
            foreach (['company','phone','address','role','sector','mode'] as $key) {
                if (isset($s[$key])) {
                    $old[$key] = sanitize_text_field($s[$key]);
                }
            }
            if (isset($s['ui_language'])) {
                $old['ui_language'] = $s['ui_language'] === 'it' ? 'it' : 'en';
            }
            if (isset($s['email'])) {
                $old['email'] = sanitize_email($s['email']);
            }
            if (isset($s['accent'])) {
                $old['accent'] = sanitize_hex_color($s['accent']) ?: '#135f54';
            }
            if (isset($s['logo'])) {
                $old['logo'] = esc_url_raw($s['logo'], ['http','https']);
            }
            if (isset($s['language'])) {
                $old['language'] = Passport::language($s['language']);
            }
            foreach (['label_width','label_height','label_margin'] as $key) {
                if (isset($s[$key])) {
                    $old[$key] = max($key === 'label_margin' ? 0 : 25, min($key === 'label_margin' ? 30 : 190, absint($s[$key])));
                }
            }
            if (isset($s['email_enabled'])) {
                $old['email_enabled'] = (bool)$s['email_enabled'];
            }
            update_option('gmpp_settings', $old, false);
            return $old;
        }, 'gmpp_manage');
        $this->route('/documents', 'GET', static function () {
            global $wpdb;
            $rows = $wpdb->get_col($wpdb->prepare('SELECT id FROM %i ORDER BY id DESC LIMIT 1000', Store::table('documents')));
            $docs = [];
            foreach ($rows as $id) {
                $doc = Documents::current((int)$id);
                if ($doc && ($doc['visibility'] === 'public' || current_user_can('gmpp_review'))) {
                    $docs[] = $doc;
                }
            } return $docs;
        });
        $this->route('/documents', 'POST', static function ($r) {
            $files = $r->get_file_params();
            return Documents::upload($files['file'] ?? [], $r->get_params());
        });
        $this->route('/documents/(?P<id>\d+)/impact', 'GET', static function ($r) {
            return Documents::affected((int)$r['id']);
        }, 'gmpp_review');
        $this->route('/import/preview', 'POST', static function ($r) {
            $preview = Jobs::csv((string)$r['csv'], (array)$r['map'], (string)$r['mode'], (string)$r['key'], (bool)$r['clear']);
            $token = wp_generate_password(32, false);
            set_transient('gmpp_import_' . get_current_user_id() . '_' . $token, $preview, HOUR_IN_SECONDS);
            return ['token' => $token,'headers' => $preview['headers'],'rows' => array_slice($preview['rows'], 0, 30),'total' => count($preview['rows']),'errors' => count(array_filter($preview['rows'], static fn($row)=>!empty($row['error'])))];
        });
        $this->route('/import/start', 'POST', static function ($r) {
            $key = 'gmpp_import_' . get_current_user_id() . '_' . sanitize_text_field($r['token']);
            $p = get_transient($key);
            if (!$p) {
                throw new \InvalidArgumentException('Anteprima scaduta. Ripeti la verifica.');
            } delete_transient($key);
            return Jobs::create('csv', $p, count($p['rows']));
        });
        $this->route('/jobs', 'GET', static function () {
            global $wpdb;
            return $wpdb->get_results($wpdb->prepare('SELECT id,kind,state,`cursor`,total,updated_at FROM %i WHERE owner=%d ORDER BY id DESC LIMIT 20', Store::table('jobs'), get_current_user_id()), ARRAY_A);
        });
        $this->route('/jobs/(?P<id>\d+)', 'GET', static function ($r) {
            $j = Store::one('jobs', (int)$r['id']);
            if (!$j || (int)$j['owner'] !== get_current_user_id()) {
                throw new \DomainException('Operazione non accessibile.');
            } unset($j['payload']);
            $j['report'] = Store::decode($j['report']);
            return $j;
        });
        $this->route('/jobs/(?P<id>\d+)/run', 'POST', static function ($r) {
            $j = Store::one('jobs', (int)$r['id']);
            if (!$j || (int)$j['owner'] !== get_current_user_id()) {
                throw new \DomainException('Operazione non accessibile.');
            } Jobs::tick();
            return ['ok' => true];
        });
        $this->route('/jobs/(?P<id>\d+)/cancel', 'POST', static function ($r) {
            global $wpdb;
            $wpdb->update(Store::table('jobs'), ['state' => 'cancelled'], ['id' => (int)$r['id'],'owner' => get_current_user_id(),'state' => 'queued']);
            return ['ok' => true];
        });
        $this->route('/requests', 'POST', static fn($r)=>Suppliers::create((int)$r['passport_id'], (array)$r['fields'], (int)($r['days'] ?: 7)), 'gmpp_review');
        $this->route('/requests/(?P<id>\d+)/review', 'POST', static fn($r)=>Suppliers::review((int)$r['id'], (bool)$r['accept'], (int)$r['lock_version']), 'gmpp_review');
        $this->route('/requests/(?P<id>\d+)/revoke', 'POST', static function ($r) {
            $req = Store::one('requests', (int)$r['id']);
            if (!$req) {
                throw new \OutOfBoundsException('Richiesta non trovata.');
            } self::access((int)$req['passport_id'], 'gmpp_review');
            global $wpdb;
            $wpdb->update(Store::table('requests'), ['state' => 'revoked'], ['id' => (int)$r['id']]);
            return ['ok' => true];
        }, 'gmpp_review');
        $this->route('/requests/(?P<id>\d+)/email', 'POST', static fn($r)=>['sent' => Suppliers::email((int)$r['id'], sanitize_email($r['email']), (string)$r['url'])], 'gmpp_review');
        $this->route('/passports/(?P<id>\d+)/requests', 'GET', static function ($r) {
            self::access((int)$r['id'], 'gmpp_review');
            global $wpdb;
            $rows = $wpdb->get_results($wpdb->prepare('SELECT id,fields,proposal,state,expires_at FROM %i WHERE passport_id=%d ORDER BY id DESC', Store::table('requests'), (int)$r['id']), ARRAY_A);
            return array_map(static function ($row) {
                $row['proposal'] = Store::decode($row['proposal']);
                return $row;
            }, $rows);
        }, 'gmpp_review');
        $this->route('/woocommerce', 'GET', static fn($r)=>Woo::products(sanitize_text_field($r['search'] ?? '')));
        $this->route('/woocommerce/(?P<id>\d+)', 'POST', static fn($r)=>Woo::import((int)$r['id']));
        $this->route('/portable', 'POST', static fn($r)=>Portable::restore((string)$r['archive'], (bool)$r['confirm']), 'gmpp_manage');
        register_rest_route('gmpp/v1', '/public/(?P<uuid>[a-f0-9-]{36})', ['methods' => 'GET','permission_callback' => '__return_true','callback' => static function ($r) {
            $snap = Passport::published($r['uuid']);
            if (!$snap) {
                return new \WP_Error('not_found', 'Scheda non disponibile.', ['status' => 404]);
            } return new \WP_REST_Response($snap, 200, ['Cache-Control' => 'no-cache','X-Content-Type-Options' => 'nosniff']);
        }]);
    }
}
