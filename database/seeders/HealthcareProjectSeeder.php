<?php

namespace Database\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Healthcare PMS - sample data seeder (matches software_pms.sql exactly).
 *
 * Place in:  database/seeders/HealthcareProjectSeeder.php
 * Run:       php artisan db:seed --class=HealthcareProjectSeeder
 *
 * - Re-runnable: it empties the project tables first (the `users` table is kept).
 * - Login created: pm@healthcare.test / password   (only if it does not exist)
 * - Stored values like score/level/slack/critical/delay/EVM are pre-filled so every
 *   screen has data on first load; your app can still recompute them.
 */
class HealthcareProjectSeeder extends Seeder
{
    /** true  = CR-002 (mobile app) already applied to scope/budget/schedule (case-study end state).
     *  false = CR-002 is 'approved' but NOT applied, so you can click "Apply" live in your demo. */
    private const APPLY_CR_002 = true;

    private const STATUS_DAY = 80;
    private const APPROVED_BUDGET = 5000000;
    private const APPROVED_DEADLINE_DAY = 112;   // 16 weeks
    private const CPI_THRESHOLD = 0.95;
    private const SPI_THRESHOLD = 0.95;
    private const BUDGET_ALERT_PCT = 90;

    private DateTimeImmutable $start;

    private function day(?int $n): ?string
    {
        return $n === null ? null : $this->start->modify("+$n days")->format('Y-m-d');
    }

    private function ins(string $table, array $row): int
    {
        $now = date('Y-m-d H:i:s');
        if (!in_array($table, ['change_request_requirements', 'activity_dependencies', 'checklist_items'])) {
            $row['created_at'] = $now;
            if (!in_array($table, ['alerts', 'evm_snapshots', 'inspection_results'])) {
                $row['updated_at'] = $now;
            }
        }
        return (int) DB::table($table)->insertGetId($row);
    }

    public function run(): void
    {
        $this->start = new DateTimeImmutable('2026-08-03');
        $SD = self::STATUS_DAY;
        $applyCr2 = self::APPLY_CR_002;

        // ------------------------------------------------------------ reset
        Schema::disableForeignKeyConstraints();
        foreach (['alerts', 'evm_snapshots', 'inspection_results', 'checklist_items', 'quality_checklists',
                  'risk_responses', 'risks', 'defects', 'cost_entries', 'activity_dependencies', 'activities',
                  'milestones', 'wbs_items', 'change_request_requirements', 'requirements', 'change_requests',
                  'stakeholders', 'team_members', 'project_plans', 'projects'] as $t) {
            DB::table($t)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        // ------------------------------------------------------------ user
        $u = DB::table('users')->where('email', 'pm@healthcare.test')->first();
        if ($u) {
            $userId = (int) $u->id;
        } else {
            $userId = $this->ins('users', ['name' => 'Ayesha Malik', 'email' => 'pm@healthcare.test',
                'password' => Hash::make('password')]);
        }

        // ------------------------------------------------------------ change requests (computed chain)
        $crDefs = [
            // code, title, description, justification, by, reqDay, decDay, applyDay, cost, days, outcome
            ['CR-001', 'AI-based preliminary diagnosis module',
                'Add a symptom-based AI module that gives clinicians a preliminary diagnosis suggestion.',
                'Hospital management wants faster triage and reduced consultation time.',
                'Hospital Management Board', 28, 33, 34, 500000, 14, 'implemented'],
            ['CR-002', 'Mobile application for patients and doctors',
                'Develop an Android/iOS app for booking appointments, viewing reports and consultations.',
                'Patients increasingly use phones; improves adoption across the five hospitals.',
                'Hospital Management Board', 45, 50, $applyCr2 ? 51 : null, 300000, 7, $applyCr2 ? 'implemented' : 'approved'],
            ['CR-003', 'Insurance claim integration',
                'Submit claims electronically to insurers from the billing module.',
                'Billing department wants to reduce manual claim processing.',
                'Billing Department', 70, null, null, 350000, 10, 'pending'],
            ['CR-004', 'Pharmacy inventory module',
                'Track pharmacy stock and dispensing inside the system.',
                'Requested by hospital IT head to centralise stock.',
                'Hospital IT Head', 62, 68, null, 420000, 14, 'rejected'],
        ];
        $budget = self::APPROVED_BUDGET;
        $deadlineDay = self::APPROVED_DEADLINE_DAY;
        $crId = [];
        $crApplied = [];
        foreach ($crDefs as [$code, $title, $desc, $just, $by, $reqD, $decD, $appD, $cost, $days, $st]) {
            $row = [
                'project_id' => 0, 'code' => $code, 'title' => $title, 'description' => $desc,
                'justification' => $just, 'requested_by' => $by, 'cost_impact' => $cost,
                'schedule_impact_days' => $days, 'status' => $st,
                'decision_notes' => match ($st) {
                    'implemented' => 'Approved by change control board; budget, deadline and scope updated.',
                    'approved' => 'Approved by change control board; awaiting application to the baseline.',
                    'rejected' => 'Rejected: outside agreed scope and would exceed available budget.',
                    default => null,
                },
                'decided_by' => $decD !== null ? $userId : null,
                'decided_at' => $decD !== null ? $this->day($decD) . ' 10:00:00' : null,
                'applied_at' => $appD !== null ? $this->day($appD) . ' 10:00:00' : null,
                'budget_before' => null, 'budget_after' => null, 'deadline_before' => null, 'deadline_after' => null,
                'created_at_day' => $reqD,
            ];
            if ($appD !== null) {
                $row['budget_before'] = $budget;
                $row['deadline_before'] = $this->day($deadlineDay);
                $budget += $cost;
                $deadlineDay += $days;
                $row['budget_after'] = $budget;
                $row['deadline_after'] = $this->day($deadlineDay);
                $crApplied[$code] = ['cost' => $cost, 'day' => $appD];
            }
            $crId[$code] = $row;
        }
        $currentBudget = $budget;
        $currentDeadlineDay = $deadlineDay;

        // ------------------------------------------------------------ project + plan + stakeholders
        $pid = $this->ins('projects', [
            'name' => 'Smart Healthcare Project Management System',
            'description' => 'Web-based system for five hospitals: patient appointments, laboratory reports, doctor schedules, online consultations and hospital billing.',
            'objectives' => "1. Deliver appointments, lab reports, doctor schedules, online consultations and billing in one system.\n2. Complete within 16 weeks.\n3. Stay within the approved budget of PKR 5,000,000.\n4. Release with zero open critical defects.",
            'scope_boundaries' => 'In scope: five core modules, role-based access, notifications, approved change requests. Out of scope: hardware procurement, insurer integration, pharmacy management (unless approved through change control).',
            'approved_budget' => self::APPROVED_BUDGET,
            'current_budget' => $currentBudget,
            'start_date' => $this->day(0),
            'approved_deadline' => $this->day(self::APPROVED_DEADLINE_DAY),
            'current_deadline' => $this->day($currentDeadlineDay),
            'status' => 'executing',
            'charter_approved' => 1,
            'charter_approved_at' => $this->day(2) . ' 09:00:00',
            'created_by' => $userId,
        ]);
        $this->ins('project_plans', [
            'project_id' => $pid,
            'scope_statement' => 'Only approved requirements are in scope. New requests follow change control and become in-scope only after approval and application.',
            'schedule_approach' => '16-week plan, 20 work packages with finish-to-start dependencies; critical path reviewed weekly.',
            'cost_approach' => 'Bottom-up activity estimates; actuals logged weekly; EVM (PV/EV/AC/CPI/SPI) reviewed against thresholds.',
            'quality_approach' => 'Checklist per deliverable, unit/integration/system/UAT testing, zero open critical defects before release.',
            'cpi_threshold' => self::CPI_THRESHOLD, 'spi_threshold' => self::SPI_THRESHOLD,
            'budget_alert_pct' => self::BUDGET_ALERT_PCT, 'version' => 2,
        ]);
        foreach ([
            ['Hospital Management Board', 'Project Sponsor', 'Healthcare Network HQ', 'high', 'high', 'board@hospitals.example'],
            ['Dr. Farah Siddiqui', 'Chief Medical Officer', 'Central City Hospital', 'high', 'medium', 'farah.siddiqui@hospitals.example'],
            ['Imran Sheikh', 'Hospital IT Head', 'Healthcare Network HQ', 'high', 'high', 'imran.sheikh@hospitals.example'],
            ['Nadia Hussain', 'Billing Department Head', 'Riverside Medical Center', 'medium', 'high', 'nadia.hussain@hospitals.example'],
            ['Patients Representative', 'End-user representative', 'Patient Welfare Committee', 'low', 'medium', null],
        ] as [$n, $r, $o, $inf, $int, $c]) {
            $this->ins('stakeholders', ['project_id' => $pid, 'name' => $n, 'role' => $r, 'organization' => $o,
                'influence' => $inf, 'interest' => $int, 'contact' => $c]);
        }

        // now insert CRs
        foreach ($crId as $code => $row) {
            $reqD = $row['created_at_day'];
            unset($row['created_at_day']);
            $row['project_id'] = $pid;
            $crId[$code] = $this->ins('change_requests', $row);
            DB::table('change_requests')->where('id', $crId[$code])
                ->update(['created_at' => $this->day($reqD) . ' 09:00:00']);
        }

        // ------------------------------------------------------------ team
        $team = [];
        foreach ([
            'pm' => ['Ayesha Malik', 'project_manager', 25000], 'be1' => ['Hamza Tariq', 'backend_developer', 18000],
            'be2' => ['Sana Iqbal', 'backend_developer', 18000], 'fe1' => ['Bilal Ahmed', 'frontend_developer', 16000],
            'fe2' => ['Rimsha Khan', 'frontend_developer', 16000], 'db' => ['Usman Raza', 'database_developer', 17000],
            'qa' => ['Hira Nasir', 'qa_engineer', 15000],
        ] as $k => [$n, $role, $rate]) {
            $team[$k] = $this->ins('team_members', ['project_id' => $pid, 'name' => $n, 'role' => $role,
                'email' => strtolower(str_replace(' ', '.', $n)) . '@devco.example', 'daily_rate' => $rate, 'is_active' => 1]);
        }

        // ------------------------------------------------------------ requirements
        $reqDefs = [
            ['REQ-001', 'Patient appointment booking and cancellation', 'functional', 'critical', 'approved', 1, null],
            ['REQ-002', 'Electronic patient record management', 'functional', 'critical', 'approved', 1, null],
            ['REQ-003', 'Doctor shift and schedule management', 'functional', 'high', 'approved', 1, null],
            ['REQ-004', 'Laboratory report upload and viewing', 'functional', 'high', 'approved', 1, null],
            ['REQ-005', 'Online video consultation', 'functional', 'medium', 'approved', 1, null],
            ['REQ-006', 'Hospital billing and invoicing', 'functional', 'high', 'approved', 1, null],
            ['REQ-007', 'Role-based access control and audit log', 'functional', 'high', 'approved', 1, null],
            ['REQ-008', 'SMS/email appointment reminders', 'functional', 'medium', 'approved', 1, null],
            ['REQ-009', 'AI-based preliminary diagnosis', 'functional', 'medium', 'approved', isset($crApplied['CR-001']) ? 1 : 0, 'CR-001'],
            ['REQ-010', 'Patient/doctor mobile application', 'functional', 'medium', 'approved', isset($crApplied['CR-002']) ? 1 : 0, 'CR-002'],
            ['REQ-011', 'Insurance claim integration', 'functional', 'low', 'proposed', 0, 'CR-003'],
            ['REQ-012', 'Pharmacy inventory module', 'functional', 'low', 'rejected', 0, 'CR-004'],
            ['REQ-013', 'Wearable-device vitals feed', 'functional', 'low', 'proposed', 0, null],
            ['REQ-014', 'System availability of 99.5% during clinic hours', 'non_functional', 'high', 'approved', 1, null],
            ['REQ-015', 'Comply with hospital patient-data privacy policy', 'constraint', 'critical', 'approved', 1, null],
            ['REQ-016', 'Offline appointment kiosk mode', 'functional', 'low', 'deferred', 0, null],
        ];
        $req = [];
        foreach ($reqDefs as [$code, $title, $type, $pri, $st, $scope, $cr]) {
            $req[$code] = $this->ins('requirements', [
                'project_id' => $pid, 'code' => $code, 'title' => $title, 'description' => $title . '.',
                'type' => $type, 'priority' => $pri, 'status' => $st, 'in_scope' => $scope,
                'origin_change_request_id' => $cr ? $crId[$cr] : null,
                'approved_by' => $st === 'approved' ? $userId : null,
                'approved_at' => $st === 'approved' ? $this->day($cr ? 33 : 8) . ' 12:00:00' : null,
            ]);
        }
        foreach (['CR-001' => 'REQ-009', 'CR-002' => 'REQ-010', 'CR-003' => 'REQ-011', 'CR-004' => 'REQ-012'] as $c => $r) {
            DB::table('change_request_requirements')->insert(['change_request_id' => $crId[$c], 'requirement_id' => $req[$r]]);
        }

        // ------------------------------------------------------------ activity definitions
        // code => [name, module, dur, preds, estCost, actStart, actEnd, pct, overrun, assignee, req]
        $T = [
            'A-001' => ['Requirements analysis & SRS', 'Planning', 10, [], 190000, 0, 9, 100, 1.03, 'pm', 'REQ-001'],
            'A-002' => ['System architecture design', 'Architecture', 7, ['A-001'], 140000, 10, 17, 100, 1.05, 'be1', 'REQ-007'],
            'A-003' => ['Database schema design', 'Database', 7, ['A-001'], 115000, 10, 16, 100, 1.00, 'db', 'REQ-002'],
            'A-004' => ['UI/UX design & prototypes', 'Frontend', 10, ['A-001'], 170000, 10, 20, 100, 1.04, 'fe1', 'REQ-001'],
            'A-005' => ['Patient record management module', 'Patient Records', 21, ['A-002', 'A-003'], 440000, 18, 40, 100, 1.14, 'be1', 'REQ-002'],
            'A-006' => ['Appointment scheduling module', 'Appointments', 21, ['A-002', 'A-003'], 440000, 18, 41, 100, 1.18, 'be2', 'REQ-001'],
            'A-007' => ['Doctor schedule management', 'Doctor Schedules', 14, ['A-006', 'A-003'], 255000, 42, 55, 100, 1.08, 'be2', 'REQ-003'],
            'A-008' => ['Authentication & role-based access', 'Security', 14, ['A-002', 'A-003'], 240000, 18, 31, 100, 1.02, 'be1', 'REQ-007'],
            'A-009' => ['Laboratory reports module', 'Laboratory', 21, ['A-005'], 420000, 41, 61, 100, 1.06, 'be1', 'REQ-004'],
            'A-010' => ['Online consultation module', 'Consultation', 17, ['A-007'], 350000, 56, null, 85, 1.12, 'be2', 'REQ-005'],
            'A-011' => ['Patient & doctor web portal', 'Frontend', 21, ['A-004', 'A-002'], 330000, 21, 41, 100, 1.05, 'fe1', 'REQ-001'],
            'A-012' => ['Admin dashboard UI components', 'Frontend', 21, ['A-004'], 310000, 21, 41, 100, 1.03, 'fe2', 'REQ-006'],
            'A-013' => ['Hospital billing module', 'Billing', 21, ['A-012'], 310000, 42, 62, 100, 1.04, 'be1', 'REQ-006'],
            'A-014' => ['API integration layer', 'Integration', 14, ['A-011', 'A-005', 'A-006'], 230000, 42, 55, 100, 1.07, 'be2', 'REQ-002'],
            'A-015' => ['System integration testing', 'QA', 14, ['A-009', 'A-010', 'A-013', 'A-014'], 275000, null, null, 0, 1.0, 'qa', 'REQ-001'],
            'A-016' => ['CI/CD & environments setup', 'DevOps', 14, ['A-001'], 185000, 10, 23, 100, 1.00, 'be2', 'REQ-014'],
            'A-017' => ['Notifications (SMS/email)', 'Integration', 14, ['A-005', 'A-006'], 240000, 42, 55, 100, 1.09, 'be2', 'REQ-008'],
            'A-018' => ['User acceptance testing (5 hospitals)', 'QA', 10, ['A-015'], 230000, null, null, 0, 1.0, 'qa', 'REQ-001'],
            'A-019' => ['Staff training & data migration', 'Deployment', 7, ['A-018'], 165000, null, null, 0, 1.0, 'pm', 'REQ-002'],
            'A-020' => ['Go-live & handover', 'Deployment', 7, ['A-019'], 210000, null, null, 0, 1.0, 'pm', 'REQ-001'],
        ];
        // change-driven packages are appended only once their change request is applied
        if (isset($crApplied['CR-001'])) {
            $T['CR-A1'] = ['AI preliminary diagnosis module (CR-001)', 'AI Diagnosis', 14, ['A-010'], 500000, null, null, 0, 1.0, 'be1', 'REQ-009'];
            $T['A-015'][3][] = 'CR-A1';
        }
        if (isset($crApplied['CR-002'])) {
            $T['CR-A2'] = ['Mobile application (CR-002)', 'Mobile', 7, ['CR-A1'], 300000, null, null, 0, 1.0, 'fe1', 'REQ-010'];
            $T['A-015'][3][] = 'CR-A2';
        }
        $T = array_map(fn($v) => array_combine(
            ['name', 'mod', 'dur', 'preds', 'cost', 'as', 'ae', 'pct', 'f', 'who', 'req'], $v), $T);

        // topological order
        $order = [];
        $left = array_keys($T);
        while ($left) {
            foreach ($left as $i => $c) {
                if (!array_diff($T[$c]['preds'], $order)) { $order[] = $c; unset($left[$i]); }
            }
        }
        // forward pass (planned), exclusive end
        $es = $ef = [];
        foreach ($order as $c) {
            $es[$c] = $T[$c]['preds'] ? max(array_map(fn($p) => $ef[$p], $T[$c]['preds'])) : 0;
            $ef[$c] = $es[$c] + $T[$c]['dur'];
        }
        $planEnd = max($ef);
        // backward pass
        $succ = [];
        foreach ($T as $c => $v) foreach ($v['preds'] as $p) $succ[$p][] = $c;
        $ls = $lf = [];
        foreach (array_reverse($order) as $c) {
            $lf[$c] = isset($succ[$c]) ? min(array_map(fn($s) => $ls[$s], $succ[$c])) : $planEnd;
            $ls[$c] = $lf[$c] - $T[$c]['dur'];
        }
        // projected pass
        $pS = $pE = [];
        foreach ($order as $c) {
            $v = $T[$c];
            if ($v['pct'] >= 100) { $pS[$c] = $v['as']; $pE[$c] = $v['ae'] + 1; }
            elseif ($v['pct'] > 0) { $pS[$c] = $v['as']; $pE[$c] = $SD + (int) ceil($v['dur'] * (1 - $v['pct'] / 100)); }
            else {
                $pS[$c] = max($SD, $v['preds'] ? max(array_map(fn($p) => $pE[$p], $v['preds'])) : 0);
                $pE[$c] = $pS[$c] + $v['dur'];
            }
        }
        $projectedFinish = max($pE);

        // ------------------------------------------------------------ milestones
        $msDefs = [
            ['SRS approved', 'A-001'], ['UI/UX design signed off', 'A-004'], ['Core backend modules complete', 'A-009'],
            ['Online consultation delivered', 'A-010'], ['Integration testing complete', 'A-015'],
            ['UAT sign-off by five hospitals', 'A-018'], ['Go-live', 'A-020'],
        ];
        if (isset($T['CR-A2'])) array_splice($msDefs, 4, 0, [['AI diagnosis & mobile app delivered', 'CR-A2']]);
        $msByAct = [];
        foreach ($msDefs as [$title, $c]) {
            $due = $ef[$c] - 1;
            $done = $T[$c]['pct'] >= 100;
            $msByAct[$c] = $this->ins('milestones', [
                'project_id' => $pid, 'title' => $title, 'description' => "Completion of {$T[$c]['name']}.",
                'due_date' => $this->day($due), 'completed_date' => $done ? $this->day($T[$c]['ae']) : null,
                'status' => $done ? 'achieved' : ($due < $SD ? 'missed' : 'pending'),
                'responsible_id' => $team['pm'],
            ]);
        }

        // ------------------------------------------------------------ WBS
        $wbs = [];
        $wbs['root'] = $this->ins('wbs_items', ['project_id' => $pid, 'parent_id' => null, 'requirement_id' => null,
            'wbs_code' => '1', 'name' => 'Smart Healthcare PM System', 'level_type' => 'project', 'sort_order' => 1]);
        $phases = ['1.1' => 'Initiation & Design', '1.2' => 'Core Modules', '1.3' => 'Frontend & Integration',
                   '1.4' => 'Testing & Deployment'];
        if (isset($T['CR-A1'])) $phases['1.5'] = 'Approved Changes';
        $so = 1;
        foreach ($phases as $code => $name) {
            $wbs[$code] = $this->ins('wbs_items', ['project_id' => $pid, 'parent_id' => $wbs['root'], 'requirement_id' => null,
                'wbs_code' => $code, 'name' => $name, 'level_type' => 'phase', 'sort_order' => $so++]);
        }
        $phaseOf = fn($m) => match ($m) {
            'Planning', 'Architecture', 'Database', 'DevOps' => '1.1',
            'Patient Records', 'Appointments', 'Doctor Schedules', 'Laboratory', 'Consultation', 'Billing', 'Security' => '1.2',
            'Frontend', 'Integration' => '1.3',
            'AI Diagnosis', 'Mobile' => '1.5',
            default => '1.4',
        };
        $cnt = [];
        $wbsOf = [];
        foreach ($order as $c) {
            $ph = $phaseOf($T[$c]['mod']);
            $cnt[$ph] = ($cnt[$ph] ?? 0) + 1;
            $wbsOf[$c] = $this->ins('wbs_items', ['project_id' => $pid, 'parent_id' => $wbs[$ph],
                'requirement_id' => $req[$T[$c]['req']], 'wbs_code' => "$ph.{$cnt[$ph]}", 'name' => $T[$c]['name'],
                'level_type' => 'work_package', 'sort_order' => $cnt[$ph]]);
        }

        // ------------------------------------------------------------ activities + dependencies + cost entries
        $aid = [];
        $costEntries = [];   // [day, amount] for EVM history
        foreach ($order as $c) {
            $v = $T[$c];
            $delay = max(0, $pE[$c] - $ef[$c]);
            $status = $v['pct'] >= 100 ? 'completed' : ($v['pct'] > 0 ? ($delay > 0 ? 'delayed' : 'in_progress') : 'not_started');
            $aid[$c] = $this->ins('activities', [
                'project_id' => $pid, 'wbs_item_id' => $wbsOf[$c], 'requirement_id' => $req[$v['req']],
                'assigned_to' => $team[$v['who']], 'milestone_id' => $msByAct[$c] ?? null,
                'code' => $c, 'name' => $v['name'], 'duration_days' => $v['dur'],
                'planned_start' => $this->day($es[$c]), 'planned_end' => $this->day($ef[$c] - 1),
                'late_start' => $this->day($ls[$c]), 'late_end' => $this->day($lf[$c] - 1),
                'slack_days' => $ls[$c] - $es[$c], 'is_critical' => ($ls[$c] - $es[$c]) === 0 ? 1 : 0,
                'actual_start' => $this->day($v['as']), 'actual_end' => $this->day($v['ae']),
                'percent_complete' => $v['pct'], 'status' => $status, 'delay_days' => $delay,
                'estimated_cost' => $v['cost'],
            ]);
            foreach ($v['preds'] as $p) {
                DB::table('activity_dependencies')->insert(['activity_id' => $aid[$c], 'predecessor_id' => $aid[$p],
                    'dependency_type' => 'FS', 'lag_days' => 0]);
            }
        }
        foreach ($order as $c) {
            $v = $T[$c];
            $actual = $v['cost'] * $v['f'] * $v['pct'] / 100;
            if ($actual <= 0) continue;
            $end = $v['ae'] ?? $SD;
            $cat = match ($v['mod']) { 'DevOps' => 'infrastructure', 'QA' => 'testing', default => 'labor' };
            $paid = 0;
            for ($i = 1; $i <= 3; $i++) {
                $amt = $i < 3 ? round($actual / 3, 2) : round($actual - $paid, 2);
                $paid += $amt;
                $d = $v['as'] + (int) round(($end - $v['as']) * $i / 3);
                $this->ins('cost_entries', ['project_id' => $pid, 'activity_id' => $aid[$c], 'entry_date' => $this->day($d),
                    'category' => $cat, 'description' => "Actual spend ($i/3) - {$v['name']}", 'amount' => $amt]);
                $costEntries[] = [$d, $amt];
            }
        }
        // one-off licence cost
        $this->ins('cost_entries', ['project_id' => $pid, 'activity_id' => $aid['A-002'], 'entry_date' => $this->day(12),
            'category' => 'license', 'description' => 'Video-consultation SDK and cloud licences', 'amount' => 85000]);
        $costEntries[] = [12, 85000];

        // ------------------------------------------------------------ defects
        $reqOfMod = ['Appointments' => 'REQ-001', 'Patient Records' => 'REQ-002', 'Doctor Schedules' => 'REQ-003',
                     'Laboratory' => 'REQ-004', 'Billing' => 'REQ-006', 'Frontend' => 'REQ-001'];
        $defects = [];
        foreach ([
            ['DEF-001', 'Appointments', 'Double booking allowed when two patients book the same slot concurrently', 'critical', 'closed', 52, 58, 'A-006', 'be2', 45000, 'Added row-level locking and unique slot constraint.'],
            ['DEF-002', 'Appointments', 'Cancelled appointments still block doctor availability', 'critical', 'in_progress', 66, null, 'A-006', 'be2', 0, null],
            ['DEF-003', 'Patient Records', 'Patient record overwritten on simultaneous edits (no versioning)', 'critical', 'verified', 55, 63, 'A-005', 'be1', 52000, 'Introduced optimistic locking and record version history.'],
            ['DEF-004', 'Patient Records', 'Records not linked across hospitals for the same CNIC', 'critical', 'open', 70, null, 'A-005', 'db', 0, null],
            ['DEF-005', 'Doctor Schedules', 'Time-zone shift on shift-roster export', 'high', 'resolved', 60, 64, 'A-007', 'be2', 9000, 'Normalised timestamps to UTC.'],
            ['DEF-006', 'Billing', 'Rounding error in invoice totals', 'high', 'open', 72, null, 'A-013', 'be1', 0, null],
            ['DEF-007', 'Frontend', 'Appointment calendar misaligned on mobile viewport', 'low', 'closed', 62, 66, 'A-011', 'fe1', 3000, 'CSS grid fix.'],
            ['DEF-008', 'Laboratory', 'PDF lab report truncates long result tables', 'medium', 'reopened', 74, null, 'A-009', 'fe2', 0, null],
        ] as [$code, $mod, $title, $sev, $st, $found, $res, $act, $who, $rework, $notes]) {
            $defects[$code] = $this->ins('defects', [
                'project_id' => $pid, 'code' => $code, 'title' => $title, 'description' => "$title (module: $mod).",
                'severity' => $sev, 'status' => $st, 'requirement_id' => $req[$reqOfMod[$mod]],
                'activity_id' => $aid[$act], 'assigned_to' => $team[$who], 'reported_by' => $team['qa'],
                'reported_on' => $this->day($found), 'resolved_on' => $this->day($res), 'resolution_notes' => $notes,
            ]);
            if ($rework > 0) {
                $this->ins('cost_entries', ['project_id' => $pid, 'activity_id' => $aid[$act], 'entry_date' => $this->day($res),
                    'category' => 'other', 'description' => "Rework for $code", 'amount' => $rework]);
                $costEntries[] = [$res, $rework];
            }
        }

        // ------------------------------------------------------------ quality checklists + inspection results
        $items = [
            ['Implements the approved requirement completely', 'inspection'],
            ['Unit tests pass with coverage of at least 80%', 'unit_test'],
            ['Peer code review completed', 'inspection'],
            ['Integration test passes with dependent modules', 'integration_test'],
            ['No open critical or high defects', 'system_test'],
            ['User documentation updated', 'inspection'],
        ];
        // checklist target => [fail item index => defect code]
        $checks = [
            'A-005' => [4 => 'DEF-004'], 'A-006' => [3 => 'DEF-001', 4 => 'DEF-002'], 'A-007' => [],
            'A-009' => [5 => 'DEF-008'], 'A-013' => [4 => 'DEF-006'], 'A-010' => null,
        ];
        foreach ($checks as $c => $fails) {
            $clId = $this->ins('quality_checklists', ['project_id' => $pid, 'wbs_item_id' => $wbsOf[$c],
                'name' => "Quality checklist - {$T[$c]['name']}"]);
            foreach ($items as $i => [$desc, $type]) {
                $itemId = DB::table('checklist_items')->insertGetId(['checklist_id' => $clId, 'description' => $desc, 'sort_order' => $i + 1]);
                if ($fails === null) continue;   // module still in progress: not inspected yet
                $failed = array_key_exists($i, $fails);
                $this->ins('inspection_results', [
                    'checklist_item_id' => $itemId, 'inspected_by' => $team['qa'], 'inspection_type' => $type,
                    'result' => $failed ? 'fail' : 'pass', 'inspected_on' => $this->day(64 + $i),
                    'notes' => $failed ? "Failed - see {$fails[$i]}." : null,
                    'defect_id' => $failed ? $defects[$fails[$i]] : null,
                ]);
            }
        }

        // ------------------------------------------------------------ risks + PDPC nodes
        foreach ([
            ['R-001', 'Further scope creep from hospital stakeholders', 'scope', 4, 4, 'identified',
                'Late feature requests (insurance, pharmacy, wearables) keep arriving.',
                'Freeze scope; route every request through the change control board.',
                'Re-baseline budget and schedule for each approved change.',
                'Defer low-priority requests to a phase-2 backlog.'],
            ['R-002', 'Unresolved critical defects delay integration testing', 'quality', 4, 5, 'occurred',
                'Critical defects remain open in appointments and patient records.',
                'Daily defect triage and earlier regression testing.',
                'Run a dedicated fix sprint with QA pairing.',
                'Add temporary developer capacity; hold the release gate.'],
            ['R-003', 'Budget exhausted before UAT (CPI below 1)', 'cost', 3, 5, 'mitigating',
                'Actual cost keeps running above earned value.',
                'Weekly EVM review with automatic threshold alerts.',
                'Cut or defer low-priority scope.',
                'Raise a budget change request to the sponsor.'],
            ['R-004', 'AI module accuracy unacceptable for clinical use', 'technical', 3, 4, 'identified',
                'Preliminary diagnosis gives unreliable suggestions.',
                'Position as decision support only with mandatory clinician review.',
                'Retrain and validate with curated hospital data.',
                'Restrict to triage hints or remove from release.'],
            ['R-005', 'Key developer becomes unavailable', 'resource', 2, 4, 'identified',
                'A developer leaves or falls ill mid-project.',
                'Pair programming and cross-training on critical modules.',
                'Reassign work within the team.',
                'Extend the affected activity and notify the sponsor.'],
            ['R-006', 'Patient data privacy or security breach', 'technical', 2, 5, 'mitigating',
                'Unauthorised access to patient records.',
                'RBAC, encryption at rest and full audit logging.',
                'Incident response plan and immediate access revocation.',
                'Notify hospital IT head and affected patients per policy.'],
        ] as [$code, $title, $cat, $lik, $imp, $st, $problem, $prev, $corr, $cont]) {
            $score = $lik * $imp;
            $rid = $this->ins('risks', [
                'project_id' => $pid, 'code' => $code, 'title' => $title, 'description' => $problem, 'category' => $cat,
                'likelihood' => $lik, 'impact' => $imp,   // `score` is a generated column - never insert it
                'level' => $score >= 15 ? 'extreme' : ($score >= 10 ? 'high' : ($score >= 5 ? 'medium' : 'low')),
                'status' => $st, 'owner_id' => $team['pm'],
            ]);
            $root = $this->ins('risk_responses', ['risk_id' => $rid, 'parent_id' => null, 'node_type' => 'potential_problem',
                'description' => $problem, 'owner_id' => $team['pm'], 'status' => 'planned']);
            $this->ins('risk_responses', ['risk_id' => $rid, 'parent_id' => $root, 'node_type' => 'preventive_action',
                'description' => $prev, 'owner_id' => $team['pm'], 'status' => 'in_progress']);
            $cor = $this->ins('risk_responses', ['risk_id' => $rid, 'parent_id' => $root, 'node_type' => 'corrective_action',
                'description' => $corr, 'owner_id' => $team['pm'], 'status' => 'planned']);
            $this->ins('risk_responses', ['risk_id' => $rid, 'parent_id' => $cor, 'node_type' => 'contingency',
                'description' => $cont, 'owner_id' => $team['pm'], 'status' => 'planned']);
        }

        // ------------------------------------------------------------ EVM history (days 20,40,60,80)
        $baseCost = array_sum(array_column(array_filter($T, fn($v, $k) => !str_starts_with($k, 'CR-'), ARRAY_FILTER_USE_BOTH), 'cost'));
        $evm = [];
        foreach ([20, 40, 60, $SD] as $t) {
            $bac = $baseCost;
            foreach ($crApplied as $a) if ($a['day'] <= $t) $bac += $a['cost'];
            $pv = $ev = 0;
            foreach ($T as $c => $v) {
                if (str_starts_with($c, 'CR-') && (!isset($crApplied[$c === 'CR-A1' ? 'CR-001' : 'CR-002']) ||
                    $crApplied[$c === 'CR-A1' ? 'CR-001' : 'CR-002']['day'] > $t)) continue;
                $pv += $v['cost'] * min(max(($t - $es[$c]) / $v['dur'], 0), 1);
                if ($v['as'] === null || $v['as'] > $t) { $p = 0; }
                elseif ($v['ae'] !== null) { $p = $v['ae'] <= $t ? 1 : ($t - $v['as'] + 1) / ($v['ae'] - $v['as'] + 1); }
                else { $p = min(1, ($v['pct'] / 100) * ($t - $v['as'] + 1) / ($SD - $v['as'] + 1)); }
                $ev += $v['cost'] * $p;
            }
            $ac = array_sum(array_map(fn($e) => $e[0] <= $t ? $e[1] : 0, $costEntries));
            $cpi = $ac > 0 ? $ev / $ac : null;
            $spi = $pv > 0 ? $ev / $pv : null;
            $eac = $cpi ? $bac / $cpi : null;
            $evm[$t] = compact('bac', 'pv', 'ev', 'ac', 'cpi', 'spi', 'eac');
            $this->ins('evm_snapshots', [
                'project_id' => $pid, 'status_date' => $this->day($t), 'bac' => round($bac, 2), 'pv' => round($pv, 2),
                'ev' => round($ev, 2), 'ac' => round($ac, 2), 'cv' => round($ev - $ac, 2), 'sv' => round($ev - $pv, 2),
                'cpi' => $cpi ? round($cpi, 4) : null, 'spi' => $spi ? round($spi, 4) : null,
                'eac' => $eac ? round($eac, 2) : null, 'etc' => $eac ? round($eac - $ac, 2) : null,
                'vac' => $eac ? round($bac - $eac, 2) : null,
            ]);
        }

        // ------------------------------------------------------------ alerts (from current figures)
        $n = $evm[$SD];
        $alerts = [];
        if ($n['cpi'] !== null && $n['cpi'] < self::CPI_THRESHOLD) {
            $alerts[] = ['cost_overrun', $n['cpi'] < 0.85 ? 'critical' : 'warning',
                sprintf('Cost overrun: CPI %.2f is below the %.2f threshold. Forecast EAC PKR %s vs budget PKR %s.',
                    $n['cpi'], self::CPI_THRESHOLD, number_format($n['eac']), number_format($currentBudget))];
        }
        if ($n['spi'] !== null && $n['spi'] < self::SPI_THRESHOLD) {
            $alerts[] = ['schedule_delay', 'warning', sprintf('Schedule delay: SPI %.2f is below the %.2f threshold.', $n['spi'], self::SPI_THRESHOLD)];
        }
        if ($n['ac'] >= $currentBudget * self::BUDGET_ALERT_PCT / 100) {
            $alerts[] = ['budget_threshold', 'critical', sprintf('Actual cost has reached %.0f%% of the current budget.', $n['ac'] / $currentBudget * 100)];
        }
        if ($projectedFinish - 1 > $currentDeadlineDay) {
            $alerts[] = ['deadline_risk', 'critical', sprintf('Projected finish (%s) is %d days after the current deadline (%s).',
                $this->day($projectedFinish - 1), $projectedFinish - 1 - $currentDeadlineDay, $this->day($currentDeadlineDay))];
        }
        $openCrit = DB::table('defects')->where('project_id', $pid)->where('severity', 'critical')
            ->whereIn('status', ['open', 'in_progress', 'reopened'])->count();
        if ($openCrit > 0) {
            $alerts[] = ['critical_defect', 'critical', "$openCrit critical defects are unresolved."];
        }
        $alerts[] = ['scope', 'info', 'Change request CR-003 (Insurance claim integration) is pending a decision.'];
        foreach ($alerts as [$type, $sev, $msg]) {
            $this->ins('alerts', ['project_id' => $pid, 'type' => $type, 'severity' => $sev, 'message' => $msg, 'is_resolved' => 0]);
        }

        if ($this->command) {
            $this->command->info(sprintf('Seeded. Budget %s -> %s | deadline %s -> %s | projected finish %s',
                number_format(self::APPROVED_BUDGET), number_format($currentBudget), $this->day(self::APPROVED_DEADLINE_DAY),
                $this->day($currentDeadlineDay), $this->day($projectedFinish - 1)));
            $this->command->info(sprintf('Day %d: PV %s | EV %s | AC %s | CPI %.3f | SPI %.3f | EAC %s',
                $SD, number_format($n['pv']), number_format($n['ev']), number_format($n['ac']), $n['cpi'], $n['spi'], number_format($n['eac'])));
        }
    }
}
