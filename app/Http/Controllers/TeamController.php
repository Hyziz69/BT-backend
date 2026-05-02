<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class TeamController extends Controller
{
    private function checkTeamLock(Team $team, User $user): ?string
    {
        // 1. Исключение: Администратор (nti_admin) может делать всё
        if ($user->account_type === 'nti_admin') {
            return null;
        }

        // 2. Ищем заявку
        $application = DB::table('applications')->where('team_id', $team->id)->first();

        if (!$application) {
            return null; // Заявки нет — состав можно менять
        }

        // 3. Если заявка есть, проверяем статус в таблице milestones.
        // Предполагаем, что milestones связаны с вызовом (call_id)
        $hasEndedMilestone = DB::table('milestones')
            ->where('application_id', $application->id)
            ->where('status', 'completed')
            ->exists();

        if ($hasEndedMilestone) {
            return null; // Программа (milestone) завершена — блокировка снимается
        }

        // Если мы дошли сюда, значит заявка есть, программа не окончена, и юзер не админ
        return 'Tím už podal prihlášku. Zmeny sú uzamknuté a môže ich vykonať iba administrátor, alebo až po skončení programu (milestone = ended).';
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'competencies'   => ['nullable', 'array'],
            'competencies.*' => ['string', 'max:50'],
        ]);
//        $user = auth()->user();
        $user = User::where('account_type', 'nti_admin')->first();

        // 1. Проверяем, не состоит ли юзер уже в другой команде
        $alreadyInTeam = DB::table('team_members')
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyInTeam) {
            return response()->json([
                'message' => 'Už ste členom iného tímu.'
            ], 422);
        }

//        $validated = $request->validated();

        try {
            // Оборачиваем в транзакцию
            $team = DB::transaction(function () use ($validated, $user) {

                // 2. Генерируем уникальный пригласительный код (6 символов, только заглавные)

                // 3. Создаем саму команду
                $newTeam = Team::create([
                    'leader_id'    => $user->id,
                    'name'         => $validated['name'],
                    'competencies' => $validated['competencies'] ?? null,
                ]);

                // 4. Добавляем создателя в таблицу team_members с ролью leader
                $newTeam->members()->attach($user->id, [
                    'role'      => 'leader',
                    'joined_at' => now(),
                ]);

                return $newTeam;
            });

            // Возвращаем ответ фронтенду с готовым кодом
            return response()->json([
                'message' => 'Tím bol úspešne vytvorený!',
                'team'    => $team
            ], 201);

        } catch (\Exception $e) {
            Log::error('Chyba pri vytváraní tímu: ' . $e->getMessage());

            return response()->json([
                'message' => 'Vyskytla sa chyba pri vytváraní tímu.'
            ], 500);
        }
    }
    public function destroy(Request $request,Team $team): JsonResponse
    {
        // ВРЕМЕННО: получаем первого студента, как и в методе store
        $validated = $request->validate(['user_id' => 'required|uuid']);
        $user = User::find($validated['user_id']);

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        // 1. Авторизация: проверка, является ли пользователь лидером этой команды
        if ($team->leader_id !== $user->id && $user->account_type !== 'nti_admin') {
            return response()->json([
                'message' => 'Nemáte oprávnenie vymazať tento tím.'
            ], 403);
        }

        if ($lockMessage = $this->checkTeamLock($team, $user)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        try {
            DB::transaction(function () use ($team) {
                DB::table('applications')->where('team_id', $team->id)->delete();
                // 2. Удаление всех связей участников с этой командой
                // Это предотвращает появление "осиротевших" записей в team_members
                $team->members()->detach();

                // 3. Удаление самой команды
                $team->delete();
            });

            return response()->json([
                'message' => 'Tím bol úspešne odstránený.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri odstraňovaní tímu: ' . $e->getMessage());

            return response()->json([
                'message' => 'Vyskytla sa chyba pri odstraňovaní tímu.'
            ], 500);
        }
    }
    public function join(Request $request): JsonResponse
    {
        // ВРЕМЕННО: берем второго студента, так как первый (лидер) уже состоит в команде.
        // Метод skip(1) пропускает первую запись.
        $user = User::where('account_type', 'student')->skip(2)->first();

        if (!$user) {
            return response()->json(['message' => 'Druhý študent nebol nájdený.'], 404);
        }

        // 1. Строгая валидация входящего кода (ожидается ровно 8 символов)
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'size:8']
        ]);

        // 2. Поиск команды по коду
        $team = Team::where('invite_code', $validated['invite_code'])->first();

        if (!$team) {
            return response()->json(['message' => 'Neplatný pozývací kód.'], 404);
        }

        // 3. Проверка на участие в любой другой команде
        $alreadyInTeam = DB::table('team_members')
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyInTeam) {
            return response()->json(['message' => 'Už ste členom nejakého tímu.'], 422);
        }

        try {
            // 4. Добавление пользователя в команду с ролью 'member'
            $team->members()->attach($user->id, [
                'role'      => 'member',
                'joined_at' => now(),
            ]);

            return response()->json([
                'message' => 'Úspešne ste sa pripojili k tímu.',
                'team'    => $team
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri pripájaní k tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }
    public function leave(Request $request, Team $team): JsonResponse
    {
        // ВРЕМЕННО: получаем ID пользователя из тела запроса для удобства тестирования.
        // Когда подключите Sanctum, замените эти две строки на: $user = auth()->user();
        $validated = $request->validate(['user_id' => 'required|uuid']);
        $user = User::find($validated['user_id']);

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        // 1. Проверяем, состоит ли пользователь вообще в этой команде
        $isMember = $team->members()->where('user_id', $user->id)->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Nie ste členom tohto tímu.'], 403);
        }

        if ($lockMessage = $this->checkTeamLock($team, $user)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        try {
            DB::transaction(function () use ($team, $user) {

                // 2. Проверяем, является ли уходящий пользователь ЛИДЕРОМ
                if ($team->leader_id === $user->id) {

                    // Логика полного расформирования команды (как в методе destroy)
                    // Удаляем зависимые заявки (чтобы не было ошибки внешнего ключа)
                    DB::table('applications')->where('team_id', $team->id)->delete();

                    // Отвязываем абсолютно всех участников
                    $team->members()->detach();

                    // Удаляем саму команду
                    $team->delete();

                } else {
                    // 3. Обычный участник: просто удаляем его связь с командой
                    $team->members()->detach($user->id);
                }
            });

            // Формируем динамическое сообщение для фронтенда
            $message = $team->leader_id === $user->id
                ? 'Tím bol úspešne rozpustený, pretože ho opustil líder.'
                : 'Úspešne ste opustili tím.';

            return response()->json(['message' => $message], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri opúšťaní tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }

    public function kickMember(Request $request, Team $team, User $user): JsonResponse
    {
        $validated = $request->validate(['leader_id' => 'required|uuid']);
        $actingUser = User::find($validated['leader_id']);

        if (!$actingUser) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        if ($team->leader_id !== $user->id && $user->account_type !== 'nti_admin') {
            return response()->json(['message' => 'Iba líder tímu môže odstraňovať členov.'], 403);
        }

        if ($team->leader_id === $user->id) {
            return response()->json(['message' => 'Nemôžete odstrániť sám seba. Ak chcete tím zrušiť, použite príslušnú funkciu.'], 422);
        }

        $isMember = $team->members()->where('user_id', $user->id)->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Tento používateľ nie je členom tímu.'], 404);
        }

        // НОВАЯ ПРОВЕРКА: Запрет на исключение, если есть заявка
        $hasApplication = DB::table('applications')->where('team_id', $team->id)->exists();
        if ($hasApplication) {
            return response()->json([
                'message' => 'Tím už podal prihlášku. Zloženie tímu je uzamknuté a členovia nemôžu byť odstraňovaní.'
            ], 403);
        }

        if ($lockMessage = $this->checkTeamLock($team, $actingUser)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        try {
            $team->members()->detach($user->id);
            return response()->json(['message' => 'Člen bol úspešne odstránený z tímu.'], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri odstraňovaní člena tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        // 1. ВРЕМЕННО: получаем ID пользователя из тела запроса для имитации авторизации
        $validatedUser = $request->validate(['user_id' => 'required|uuid']);
        $actingUser = User::find($validatedUser['user_id']);

        if (!$actingUser) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        // 2. Проверка прав: изменять данные может ТОЛЬКО лидер команды или администратор
        if ($team->leader_id !== $actingUser->id && $actingUser->account_type !== 'nti_admin') {
            return response()->json(['message' => 'Nemáte oprávnenie upravovať tento tím.'], 403);
        }

        // 3. Проверка блокировки: нельзя менять данные, если заявка уже подана (вызов нашего хелпера)
        if ($lockMessage = $this->checkTeamLock($team, $actingUser)) {
            return response()->json(['message' => $lockMessage], 403);
        }

        // 4. Валидация новых данных
        $validatedData = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'competencies' => 'nullable|array',
            'competencies.*' => 'string|max:50', // Каждая компетенция должна быть строкой
        ]);

        try {
            // 5. Обновление записи
            // Метод update() автоматически отфильтрует поля, которых нет в $fillable модели Team
            $team->update($validatedData);

            return response()->json([
                'message' => 'Tím bol úspešne aktualizovaný.',
                'team'    => $team->fresh() // Возвращаем свежие данные из базы
            ], 200);

        } catch (\Exception $e) {
            Log::error('Chyba pri aktualizácii tímu: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }
    public function show(Request $request, Team $team): JsonResponse
    {
        // Получение ID пользователя из параметров запроса (Query String)
        $request->validate(['user_id' => 'required|uuid']);
        $user = User::find($request->query('user_id'));

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        // Проверка прав доступа: просматривать могут только участники или администратор
        $isMember = $team->members()->where('user_id', $user->id)->exists();

        $allowedRoles = ['mentor', 'company_contact', 'editor', 'nti_admin', 'superadmin'];

        if (!$isMember && !in_array($user->account_type, $allowedRoles)) {
            return response()->json(['message' => 'Nemáte oprávnenie zobraziť tento tím.'], 403);
        }

        // Жадная загрузка (Eager Loading) связанных данных для предотвращения проблемы N+1.
        // Выбираем только необходимые поля для безопасности.
        $team->load([
            'leader:id,first_name,last_name,email',
            'members:id,first_name,last_name,email'
        ]);

        // Скрытие invite_code для всех, кроме лидера и администратора
        if ($team->leader_id !== $user->id && $user->account_type !== 'nti_admin') {
            $team->makeHidden('invite_code');
        }

        return response()->json([
            'team' => $team
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|uuid']);
        $user = User::find($request->query('user_id'));

        if (!$user) {
            return response()->json(['message' => 'Používateľ nenájdený.'], 404);
        }

        $allowedRoles = ['mentor', 'company_contact', 'editor', 'nti_admin', 'superadmin'];
        $hasElevatedPrivileges = in_array($user->account_type, $allowedRoles);

        // Извлечение данных с жадной загрузкой и пагинацией (по 15 записей на страницу)
        $teams = Team::with([
            'leader:id,first_name,last_name,email',
            'members:id,first_name,last_name,email'
        ])->paginate(15);

        // Фильтрация конфиденциальных данных для обычных студентов
        if (!$hasElevatedPrivileges) {
            $teams->getCollection()->transform(function ($team) use ($user) {
                // Код остается видимым только если текущий пользователь является лидером этой команды
                if ($team->leader_id !== $user->id) {
                    $team->makeHidden('invite_code');
                }
                return $team;
            });
        }

        return response()->json($teams, 200);
    }
}
