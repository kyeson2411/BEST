<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Paginator;
use Sibol\Core\Response;
use Sibol\Core\Uploads;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Ingestion;
use Sibol\Services\Materials;
use Sibol\Services\ReadingVersions;
use Sibol\Services\SectionCopies;

final class MaterialController extends Controller
{
    use CopiesToSections;

    public function index(): Response
    {
        $user = $this->user();
        $classes = TeacherRepo::classes($user, true);
        $classIds = array_map(static fn ($c) => (int) $c['id'], $classes);
        $classId = $this->request->int('class_id');
        if ($classId !== null && !in_array($classId, $classIds, true)) {
            $classId = null;
        }
        $visibility = $this->request->str('visibility');
        $q = $this->request->str('q');
        $rows = [];
        $pager = new Paginator(0, 1, 25);
        if ($classIds) {
            [$in, $params] = Db::in($classId !== null ? [$classId] : $classIds, 'c');
            $where = "m.class_id IN $in";
            if ($visibility === 'scheduled') {
                $where .= " AND m.visibility = 'published' AND m.release_at > UTC_TIMESTAMP()";
            } elseif (in_array($visibility, ['draft', 'published', 'archived'], true)) {
                $where .= ' AND m.visibility = :vis';
                $params['vis'] = $visibility;
            } else {
                $where .= " AND m.visibility <> 'archived'";
            }
            if ($this->request->str('status') === 'problem') {
                $where .= " AND mv.processing_status IN ('failed','unsupported')";
            }
            if ($q !== '') {
                $where .= ' AND (m.title LIKE :q1 OR m.topic LIKE :q2 OR m.description LIKE :q3)';
                $like = Db::like($q);
                $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
            }
            $total = (int) Db::value("SELECT COUNT(*) FROM learning_materials m LEFT JOIN material_versions mv ON mv.id = m.current_version_id WHERE $where", $params);
            $pager = new Paginator($total, $this->page(), 25);
            $sort = $this->request->str('sort') === 'title' ? 'm.title ASC' : 'm.updated_at DESC';
            $rows = Db::all(
                "SELECT m.*, c.name AS class_name, mo.title AS module_title, mv.extension, mv.size_bytes, mv.processing_status, mv.version_no
                 FROM learning_materials m JOIN classes c ON c.id = m.class_id LEFT JOIN modules mo ON mo.id = m.module_id
                 LEFT JOIN material_versions mv ON mv.id = m.current_version_id
                 WHERE $where ORDER BY $sort" . $pager->limitSql(),
                $params
            );
        }
        return $this->view('teacher/materials', [
            'title' => t('Materials'),
            'rows' => $rows,
            'classes' => $classes,
            'classId' => $classId,
            'visibility' => $visibility,
            'q' => $q,
            'pager' => $pager,
            'sort' => $this->request->str('sort'),
        ]);
    }

    public function create(): Response
    {
        $user = $this->user();
        $classes = array_values(array_filter(TeacherRepo::classes($user), static fn ($c) => $c['status'] === 'active'));
        return $this->view('teacher/material_form', [
            'title' => t('Add material'),
            'crumbs' => [[t('Materials'), '/teacher/materials'], [t('Add material'), null]],
            'classes' => $classes,
            'modules' => $this->modulesFor(array_map(static fn ($c) => (int) $c['id'], $classes)),
            'selectedClass' => $this->request->int('class_id'),
            'maxMb' => (int) round(Uploads::maxBytes() / 1048576),
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $data = $this->validate([
            'class_id' => 'required|int',
            'module_id' => 'nullable|int',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'topic' => 'nullable|string|max:120',
            'kind' => 'required|in:file,link',
            'url' => 'nullable|url|max:500',
            'release' => 'required|in:now,draft,schedule',
            'release_at' => 'nullable|datetime',
            'ai_enabled' => 'bool',
        ], ['class_id' => t('Class'), 'title' => t('Title'), 'url' => t('Web address'), 'release_at' => t('Release date')], '/teacher/materials/new');
        $class = Access::teacherClass($user, (int) $data['class_id']);
        if ($class['status'] !== 'active') {
            $this->fail('class_id', t('That class is archived.'), '/teacher/materials/new');
        }
        $moduleId = $this->checkModule($data['module_id'] ? (int) $data['module_id'] : null, (int) $class['id'], '/teacher/materials/new');
        $releaseAt = null;
        if ($data['release'] === 'schedule') {
            $releaseAt = Dates::toUtc((string) $data['release_at']);
            if ($releaseAt === null || $releaseAt <= Db::now()) {
                $this->fail('release_at', t('Choose a release date and time in the future.'), '/teacher/materials/new');
            }
        }
        $meta = [
            'organization_id' => (int) $user['organization_id'],
            'class_id' => (int) $class['id'],
            'module_id' => $moduleId,
            'title' => $data['title'],
            'description' => $data['description'],
            'topic' => $data['topic'],
            'ai_enabled' => (bool) $data['ai_enabled'],
            'release_at' => $releaseAt,
        ];
        if ($data['kind'] === 'link') {
            if (empty($data['url'])) {
                $this->fail('url', t('Enter the web address.'), '/teacher/materials/new');
            }
            $id = Materials::createLink($meta, (string) $data['url'], (int) $user['id']);
        } else {
            $file = $this->request->file('file');
            $problem = Uploads::validate($file, Uploads::MATERIAL);
            if ($problem !== null) {
                $this->fail('file', $problem, '/teacher/materials/new');
            }
            $stored = Uploads::store($file, 'materials');
            [$id] = Materials::createFile($meta, $stored, (int) $user['id']);
        }
        Audit::log('material.created', 'material', $id, ['kind' => $data['kind']]);
        if ($data['release'] !== 'draft') {
            Materials::publish($id);
        }
        $this->flash('success', $data['kind'] === 'file'
            ? t('Uploaded. B.E.S.T. Learning and Interactive Study System is reading the file so the companion can use it.')
            : t('Link added.'));
        return $this->redirect('/teacher/materials/' . $id);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $material = Access::teacherMaterial($user, $id);
        $version = $material['current_version_id'] ? Db::one('SELECT * FROM material_versions WHERE id = :v', ['v' => (int) $material['current_version_id']]) : null;
        return $this->view('teacher/material', [
            'title' => $material['title'],
            'crumbs' => [[t('Materials'), '/teacher/materials'], [$material['title'], null]],
            'material' => $material,
            'version' => $version,
            'versions' => Db::all('SELECT mv.*, u.given_name, u.family_name, u.preferred_name FROM material_versions mv LEFT JOIN users u ON u.id = mv.uploaded_by WHERE mv.material_id = :m ORDER BY mv.version_no DESC', ['m' => $id]),
            'chunks' => $version ? Db::all('SELECT page_from, page_to, section_title, content FROM material_chunks WHERE material_version_id = :v ORDER BY chunk_index LIMIT 40', ['v' => (int) $version['id']]) : [],
            'chunkCount' => $version ? Ingestion::chunkCount((int) $version['id']) : 0,
            'modules' => Db::all('SELECT id, title FROM modules WHERE class_id = :c ORDER BY position', ['c' => (int) $material['class_id']]),
            'views' => (int) Db::value("SELECT COUNT(DISTINCT user_id) FROM content_views WHERE content_type = 'material' AND content_id = :m", ['m' => $id]),
            'students' => (int) Db::value("SELECT COUNT(*) FROM class_memberships WHERE class_id = :c AND role = 'student' AND status = 'active'", ['c' => (int) $material['class_id']]),
            'maxMb' => (int) round(Uploads::maxBytes() / 1048576),
            'copyPanel' => SectionCopies::panel($user, 'material', $material),
            'readingVersions' => ReadingVersions::forMaterial($id),
            'scripts' => ['materials.js'],
        ]);
    }

    /** Copies the material into other sections the teacher teaches, as drafts. */
    public function copy(int $id): Response
    {
        Access::teacherMaterial($this->user(), $id);
        $this->copyToSections('material', $id, '/teacher/materials/' . $id);
        return $this->redirect('/teacher/materials/' . $id);
    }

    public function update(int $id): Response
    {
        $user = $this->user();
        $material = Access::teacherMaterial($user, $id);
        $back = '/teacher/materials/' . $id;
        $data = $this->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'topic' => 'nullable|string|max:120',
            'module_id' => 'nullable|int',
            'release_at' => 'nullable|datetime',
            'ai_enabled' => 'bool',
            'url' => 'nullable|url|max:500',
        ], ['title' => t('Title'), 'release_at' => t('Release date'), 'url' => t('Web address')], $back);
        $update = [
            'title' => $data['title'],
            'description' => $data['description'],
            'topic' => $data['topic'],
            'module_id' => $this->checkModule($data['module_id'] ? (int) $data['module_id'] : null, (int) $material['class_id'], $back),
            'release_at' => $data['release_at'] ? Dates::toUtc((string) $data['release_at']) : null,
            'ai_enabled' => $material['kind'] === 'file' && $data['ai_enabled'] ? 1 : 0,
            'updated_at' => Db::now(),
        ];
        if ($material['kind'] === 'link' && !empty($data['url'])) {
            $update['url'] = $data['url'];
        }
        Db::update('learning_materials', $update, ['id' => $id]);
        Audit::log('material.updated', 'material', $id);
        $this->flash('success', t('Material details saved.'));
        return $this->redirect($back);
    }

    public function visibility(int $id): Response
    {
        $user = $this->user();
        $material = Access::teacherMaterial($user, $id);
        $action = $this->request->str('action');
        switch ($action) {
            case 'publish':
                Materials::publish($id);
                $this->flash('success', $material['release_at'] && $material['release_at'] > Db::now()
                    ? t('Published. Students will see it from :date.', ['date' => fmt_date($material['release_at'], 'datetime_short')])
                    : t('Published. Students in :class can now open it.', ['class' => $material['class_name']]));
                break;
            case 'unpublish':
                Db::update('learning_materials', ['visibility' => 'draft', 'updated_at' => Db::now()], ['id' => $id]);
                Audit::log('material.unpublished', 'material', $id);
                $this->flash('success', t('Moved back to draft. Students and the companion can no longer use it.'));
                break;
            case 'archive':
                Db::update('learning_materials', ['visibility' => 'archived', 'updated_at' => Db::now()], ['id' => $id]);
                Audit::log('material.archived', 'material', $id);
                $this->flash('success', t('Archived. It is hidden from students and the companion, and can be restored.'));
                break;
            case 'restore':
                Db::update('learning_materials', ['visibility' => 'draft', 'updated_at' => Db::now()], ['id' => $id]);
                $this->flash('success', t('Restored as a draft.'));
                break;
            default:
                throw new HttpException(400, t('Unknown action.'));
        }
        return $this->redirect('/teacher/materials/' . $id);
    }

    public function uploadVersion(int $id): Response
    {
        $user = $this->user();
        $material = Access::teacherMaterial($user, $id);
        if ($material['kind'] !== 'file') {
            throw HttpException::notFound();
        }
        $file = $this->request->file('file');
        $problem = Uploads::validate($file, Uploads::MATERIAL);
        if ($problem !== null) {
            $this->fail('file', $problem, '/teacher/materials/' . $id);
        }
        $stored = Uploads::store($file, 'materials');
        $versionId = Materials::addVersion($id, $stored, (int) $user['id']);
        Audit::log('material.version_uploaded', 'material', $id, ['version_id' => $versionId]);
        $this->flash('success', t('New version uploaded. Students and the companion will use it once it has been read.'));
        return $this->redirect('/teacher/materials/' . $id);
    }

    public function process(int $id): Response
    {
        $user = $this->user();
        $material = Access::teacherMaterial($user, $id);
        if (!$material['current_version_id']) {
            throw HttpException::notFound();
        }
        $version = Db::one('SELECT * FROM material_versions WHERE id = :v', ['v' => (int) $material['current_version_id']]);
        if ($version['processing_status'] === 'failed') {
            Db::update('material_versions', ['processing_status' => 'uploaded'], ['id' => (int) $version['id']]);
        }
        $result = Ingestion::process((int) $version['id']);
        if ($this->request->wantsJson()) {
            return $this->json($this->statusPayload((int) $version['id']));
        }
        $this->flash($result['status'] === 'ready' ? 'success' : 'error', $result['status'] === 'ready'
            ? tp('Ready: :count passage indexed for the companion.', 'Ready: :count passages indexed for the companion.', $result['chunks'])
            : (string) $result['error']);
        return $this->redirect('/teacher/materials/' . $id);
    }

    public function status(int $id): Response
    {
        $user = $this->user();
        $material = Access::teacherMaterial($user, $id);
        if (!$material['current_version_id']) {
            return $this->json(['status' => 'link']);
        }
        return $this->json($this->statusPayload((int) $material['current_version_id']));
    }

    /** @return array<string,mixed> */
    private function statusPayload(int $versionId): array
    {
        $v = Db::one('SELECT processing_status, processing_error, page_count FROM material_versions WHERE id = :v', ['v' => $versionId]);
        [$label, $tone] = Ingestion::statusLabel((string) $v['processing_status']);
        return ['status' => $v['processing_status'], 'label' => $label, 'tone' => $tone, 'error' => $v['processing_error'], 'chunks' => Ingestion::chunkCount($versionId), 'pages' => $v['page_count']];
    }

    private function checkModule(?int $moduleId, int $classId, string $back): ?int
    {
        if ($moduleId === null) {
            return null;
        }
        if (!(int) Db::value('SELECT COUNT(*) FROM modules WHERE id = :m AND class_id = :c', ['m' => $moduleId, 'c' => $classId])) {
            $this->fail('module_id', t('Choose a module from the same class.'), $back);
        }
        return $moduleId;
    }

    /** @param list<int> $classIds @return list<array<string,mixed>> */
    private function modulesFor(array $classIds): array
    {
        if (!$classIds) {
            return [];
        }
        [$in, $p] = Db::in($classIds, 'c');
        return Db::all("SELECT id, title, class_id FROM modules WHERE class_id IN $in ORDER BY position", $p);
    }
}
