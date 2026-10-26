<?php

namespace BookStack\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VlexWorkspaceController extends Controller
{
    private function nodes()
    {
        abort_unless($this->isSignedIn(), 403);
        return DB::table('vlex_nodes')->where('user_id', user()->id);
    }

    private function node($id, $trashed = false)
    {
        $query = $this->nodes()->where('id', $id);
        if (!$trashed) {
            $query->whereNull('deleted_at');
        }
        $node = $query->first();
        abort_unless($node, 404);
        return $node;
    }

    private function folder($id)
    {
        if (!$id) {
            return null;
        }
        $folder = $this->node($id);
        abort_unless($folder->kind === 'folder', 422);
        return $folder;
    }

    public function index(Request $request)
    {
        $current = $request->filled('node') ? $this->node($request->input('node')) : null;
        $parent = $current && $current->kind !== 'folder' ? $current->parent_id : ($current->id ?? null);
        $mode = $request->input('mode', 'folder');
        $query = $this->nodes();
        if ($mode === 'trash') {
            $query->whereNotNull('deleted_at');
        } else {
            $query->whereNull('deleted_at');
            if ($mode === 'favourites') {
                $query->where('favourite', true);
            } elseif (!$request->filled('q')) {
                $query->where('parent_id', $parent);
            }
        }
        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . mb_substr($request->input('q'), 0, 200) . '%');
        }
        $crumbs = [];
        $ancestor = $current;
        while ($ancestor) {
            array_unshift($crumbs, $ancestor);
            $ancestor = $ancestor->parent_id ? $this->node($ancestor->parent_id, true) : null;
        }
        $folders = $this->nodes()->where('kind', 'folder')->whereNull('deleted_at')->orderBy('name')->get();
        $versions = $current && $current->kind === 'file'
            ? DB::table('vlex_versions')->where('node_id', $current->id)->orderByDesc('id')->get() : collect();
        return view('vlex.workspace', [
            'current' => $current, 'parent' => $parent, 'mode' => $mode,
            'items' => $query->orderBy('kind')->orderBy('name')->paginate(40)->withQueryString(),
            'folders' => $folders, 'tree' => $folders->groupBy('parent_id'),
            'crumbs' => $crumbs, 'versions' => $versions,
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'kind' => 'required|in:folder,page',
            'parent_id' => 'nullable|integer', 'content' => 'nullable|string|max:1000000',
        ]);
        $folder = $this->folder($data['parent_id'] ?? null);
        $id = $this->nodes()->insertGetId([
            'user_id' => user()->id, 'parent_id' => $folder->id ?? null,
            'kind' => $data['kind'], 'name' => $data['name'], 'content' => $data['content'] ?? '',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect('/vlex?node=' . $id);
    }

    public function upload(Request $request)
    {
        $request->validate(['parent_id' => 'nullable|integer', 'node_id' => 'nullable|integer', 'document' => 'required|file|max:20480']);
        $file = $request->file('document');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['doc', 'docx', 'docm', 'dot', 'dotx', 'dotm', 'xls', 'xlsx', 'xlsm', 'xlsb', 'xlt', 'xltx', 'xltm', 'csv', 'pdf', 'rtf', 'odt', 'ods'])) {
            throw ValidationException::withMessages(['document' => 'Chỉ nhận tài liệu Word, Excel, PDF, CSV, RTF hoặc OpenDocument.']);
        }
        $folder = $this->folder($request->input('parent_id'));
        $node = $request->filled('node_id') ? $this->node($request->input('node_id')) : null;
        abort_if($node && $node->kind !== 'file', 422);
        $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
        $path = $file->store('vlex/' . user()->id, 'local_secure_attachments');
        try {
            $id = DB::transaction(function () use ($node, $folder, $file, $path, $name) {
                $id = $node->id ?? $this->nodes()->insertGetId([
                    'user_id' => user()->id, 'parent_id' => $folder->id ?? null, 'kind' => 'file',
                    'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('vlex_versions')->insert([
                    'node_id' => $id, 'path' => $path, 'original_name' => $name,
                    'size' => $file->getSize(), 'created_at' => now(),
                ]);
                $this->nodes()->where('id', $id)->update(['updated_at' => now()]);
                return $id;
            });
        } catch (\Throwable $error) {
            Storage::disk('local_secure_attachments')->delete($path);
            throw $error;
        }
        return redirect('/vlex?node=' . $id);
    }

    public function update(Request $request, $id)
    {
        $node = $this->node($id, true);
        $request->validate(['action' => 'required|in:edit,move,favourite,trash,restore', 'name' => 'sometimes|required|string|max:255', 'content' => 'nullable|string|max:1000000', 'parent_id' => 'nullable|integer']);
        $action = $request->input('action');
        $changes = ['updated_at' => now()];
        if ($action === 'edit') {
            $changes['name'] = $request->input('name', $node->name);
            if ($node->kind === 'page') {
                $changes['content'] = $request->input('content', $node->content);
            }
        } elseif ($action === 'move') {
            $target = $this->folder($request->input('parent_id'));
            $ancestor = $target;
            while ($ancestor) {
                if ((int) $ancestor->id === (int) $id) {
                    throw ValidationException::withMessages(['parent_id' => 'Không thể chuyển thư mục vào chính nó hoặc thư mục con.']);
                }
                $ancestor = $ancestor->parent_id ? $this->node($ancestor->parent_id) : null;
            }
            $changes['parent_id'] = $target->id ?? null;
        } elseif ($action === 'favourite') {
            $changes['favourite'] = !$node->favourite;
        } else {
            // Descendants follow a folder into trash. Restoring returns this batch only.
            $stamp = $action === 'trash' ? now()->format('Y-m-d H:i:s') : null;
            $ids = [(int) $id];
            $frontier = $ids;
            while ($frontier) {
                $children = $this->nodes()->whereIn('parent_id', $frontier);
                if ($action === 'restore') {
                    $children->where('deleted_at', $node->deleted_at);
                } else {
                    $children->whereNull('deleted_at');
                }
                $frontier = $children->pluck('id')->all();
                $ids = array_merge($ids, $frontier);
            }
            $restoreParent = $node->parent_id ? $this->node($node->parent_id, true) : null;
            if ($action === 'restore' && $restoreParent && $restoreParent->deleted_at) {
                $this->nodes()->where('id', $id)->update(['parent_id' => null]);
            }
            $this->nodes()->whereIn('id', $ids)->update(['deleted_at' => $stamp, 'updated_at' => now()]);
            return redirect('/vlex?mode=' . ($action === 'trash' ? 'trash' : 'folder'));
        }
        abort_if($node->deleted_at, 422);
        $this->nodes()->where('id', $id)->update($changes);
        return redirect('/vlex?node=' . $id);
    }

    public function download(Request $request, $id)
    {
        $node = $this->node($id);
        abort_unless($node->kind === 'file', 404);
        $query = DB::table('vlex_versions')->where('node_id', $id);
        if ($request->filled('version')) {
            $query->where('id', $request->input('version'));
        }
        $version = $query->orderByDesc('id')->first();
        abort_unless($version && Storage::disk('local_secure_attachments')->exists($version->path), 404);
        if ($request->boolean('preview') && strtolower(pathinfo($version->original_name, PATHINFO_EXTENSION)) === 'pdf') {
            abort_unless(file_get_contents(Storage::disk('local_secure_attachments')->path($version->path), false, null, 0, 5) === '%PDF-', 415);
            return response()->file(Storage::disk('local_secure_attachments')->path($version->path), ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
        }
        return Storage::disk('local_secure_attachments')->download($version->path, $version->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function demo()
    {
        abort_unless($this->isSignedIn(), 403);
        if (!$this->nodes()->exists()) {
            DB::transaction(function () {
                foreach (['5GC', '5GC_UPF', 'CRBT', 'eNodeB', 'EPC 1.0', 'EPC 2.0', 'EPC3.0', 'IMS', 'MSC', 'OCS 3.0', 'OCS3.0 VTT', 'ONT', 'SITE ROUTER', 'SMSC', 'STRACKING', 'TÀI LIỆU CHƯA BÀN GIAO VỚI KHÁCH HÀNG', 'TÀI LIỆU ĐÃ BÀN GIAO VỚI KHÁCH HÀNG', 'Tài liệu nội bộ TT DVSBH', 'Tài liệu Sản phẩm', 'test', 'VIPPHONE', 'vOCS4.0'] as $name) {
                    $root = $this->nodes()->insertGetId(['user_id' => user()->id, 'name' => $name, 'kind' => 'folder', 'created_at' => now(), 'updated_at' => now()]);
                    if ($name === 'test') {
                        $child = $this->nodes()->insertGetId(['user_id' => user()->id, 'parent_id' => $root, 'name' => 'test 19012024', 'kind' => 'folder', 'created_at' => now(), 'updated_at' => now()]);
                        $leaf = $this->nodes()->insertGetId(['user_id' => user()->id, 'parent_id' => $child, 'name' => 'vbfbfbfg', 'kind' => 'folder', 'created_at' => now(), 'updated_at' => now()]);
                        foreach (['New Page', 'New Page 2', 'New Page 3'] as $page) {
                            $this->nodes()->insert(['user_id' => user()->id, 'parent_id' => $leaf, 'name' => $page, 'kind' => 'page', 'content' => "TÀI LIỆU MÔ PHỎNG VLEX\n\nĐây là nội dung mẫu để thử luồng thư mục và trang.\n\nBạn có thể sửa trang, tải tài liệu Word/Excel/PDF lên thư mục hoặc mở khu vực BookStack để nhập Word thành trang có định dạng.", 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
            });
        }
        return redirect('/vlex');
    }
}
