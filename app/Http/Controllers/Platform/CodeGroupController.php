<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreCodeDefinitionRequest;
use App\Http\Requests\Platform\StoreCodeGroupRequest;
use App\Http\Requests\Platform\UpdateCodeDefinitionRequest;
use App\Http\Requests\Platform\UpdateCodeGroupRequest;
use App\Models\CodeDefinition;
use App\Models\CodeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 공통 코드 그룹/코드 정의 CRUD. (GAP-10, platform 전용)
 * 코드 그룹(code_groups) + 그 하위 코드 정의(code_definitions)를 한 화면에서 관리.
 */
class CodeGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CodeGroup::class);

        $search = trim((string) $request->input('search', ''));

        $codeGroups = CodeGroup::query()
            ->withCount('definitions')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('group_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('group_code')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/CodeGroups/Index', [
            'codeGroups' => $codeGroups,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', CodeGroup::class);

        return Inertia::render('Platform/CodeGroups/Create');
    }

    public function store(StoreCodeGroupRequest $request): RedirectResponse
    {
        $codeGroup = CodeGroup::create($request->validated());

        return redirect()
            ->route('platform.code-groups.show', $codeGroup)
            ->with('success', "코드 그룹 [{$codeGroup->name}] 을(를) 등록했습니다.");
    }

    public function show(Request $request, CodeGroup $codeGroup): Response
    {
        $this->authorize('view', $codeGroup);

        $codeGroup->load(['definitions' => fn ($q) => $q->orderBy('sort_order')->orderBy('code')]);

        return Inertia::render('Platform/CodeGroups/Show', [
            'codeGroup' => $codeGroup,
            'can' => [
                'update' => $request->user()->can('update', $codeGroup),
                'delete' => $request->user()->can('delete', $codeGroup),
                'manageDefinitions' => $request->user()->can('manageDefinitions', $codeGroup),
            ],
        ]);
    }

    public function edit(CodeGroup $codeGroup): Response
    {
        $this->authorize('update', $codeGroup);

        return Inertia::render('Platform/CodeGroups/Edit', [
            'codeGroup' => $codeGroup,
        ]);
    }

    public function update(UpdateCodeGroupRequest $request, CodeGroup $codeGroup): RedirectResponse
    {
        $codeGroup->update($request->validated());

        return redirect()
            ->route('platform.code-groups.show', $codeGroup)
            ->with('success', '코드 그룹 정보를 수정했습니다.');
    }

    public function destroy(CodeGroup $codeGroup): RedirectResponse
    {
        $this->authorize('delete', $codeGroup);

        // code_definitions.group_code FK 가 restrictOnDelete — 하위 코드가 있으면 삭제 불가
        if ($codeGroup->definitions()->exists()) {
            return back()->with('error', '하위 코드가 있는 코드 그룹은 삭제할 수 없습니다. 코드를 먼저 삭제하세요.');
        }

        $codeGroup->delete();

        return redirect()
            ->route('platform.code-groups.index')
            ->with('success', "코드 그룹 [{$codeGroup->name}] 을(를) 삭제했습니다.");
    }

    /** 코드 정의 추가 — group_code 는 부모 그룹에서 주입 */
    public function storeDefinition(StoreCodeDefinitionRequest $request, CodeGroup $codeGroup): RedirectResponse
    {
        $codeGroup->definitions()->create([
            ...$request->validated(),
            'group_code' => $codeGroup->group_code,
        ]);

        return redirect()
            ->route('platform.code-groups.show', $codeGroup)
            ->with('success', '코드를 추가했습니다.');
    }

    /** 코드 정의 수정 */
    public function updateDefinition(UpdateCodeDefinitionRequest $request, CodeGroup $codeGroup, CodeDefinition $definition): RedirectResponse
    {
        $this->ensureDefinitionBelongs($codeGroup, $definition);

        $definition->update($request->validated());

        return redirect()
            ->route('platform.code-groups.show', $codeGroup)
            ->with('success', '코드를 수정했습니다.');
    }

    /** 코드 정의 삭제 */
    public function destroyDefinition(Request $request, CodeGroup $codeGroup, CodeDefinition $definition): RedirectResponse
    {
        $this->authorize('manageDefinitions', $codeGroup);
        $this->ensureDefinitionBelongs($codeGroup, $definition);

        $definition->delete();

        return redirect()
            ->route('platform.code-groups.show', $codeGroup)
            ->with('success', '코드를 삭제했습니다.');
    }

    /** 대상 코드 정의가 해당 코드 그룹 소속인지 확인 — 아니면 404 */
    protected function ensureDefinitionBelongs(CodeGroup $codeGroup, CodeDefinition $definition): void
    {
        abort_unless($definition->group_code === $codeGroup->group_code, 404);
    }
}
