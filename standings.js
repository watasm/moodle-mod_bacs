class StandingsStudent {
    constructor (student, tasks) {
        this.student = student;
        this.tasks = tasks;
        this.rank_position = 42;

        console.log(this.student);
        
        this.submits = [];
    }

    get firstname () {
        return this.student.firstname;
    }

    get lastname () {
        return this.student.lastname;
    }

    get fullname () {
        return this.student.firstname + ' ' + this.student.lastname;
    }

    get user_id() {
        return this.student.id;
    }

    get start_time_cut() {
        return this.student.starttime;
    }

    get end_time_cut() {
        return this.student.endtime;
    }

    add_submit (submit) {
        this.submits.push(submit);
    }

    build_results(hide_upsolving) {
        this.active = false;

        this.results = [];
        for (var task of this.tasks) {
            this.results.push({
                task: task,

                accepted: false,
                is_first_accepted: false,
                judged: true,
                attempts: 0,
                attempts_upto_best: 0,
                points: 0,
                best_submit_time: 0,
                last_submit_time: 0,
                penalty_time: 0,
            });
        }

        for (var submit of this.submits) {
            if (!submit.task) continue;
            if (hide_upsolving && this.end_time_cut <= submit.submit_time) continue;

            this.active = true;
            
            var idx = submit.task.task_order-1;
            var result = this.results[idx];

            result.attempts += 1;
            result.last_submit_time = Math.max(0, Math.floor( (submit.submit_time - this.start_time_cut)/60 ));

            console.log(this);

            if (result.points < submit.points) {
                result.points = submit.points;
                result.attempts_upto_best = result.attempts;

                result.best_submit_time = result.last_submit_time;

                var prev_attempts = result.attempts_upto_best - 1;

                result.penalty_time = result.best_submit_time + 20 * prev_attempts;
            }
            
            if (submit.accepted) result.accepted = true;
            if (submit.is_first_accepted) result.is_first_accepted = true;

            if (!submit.judged) result.judged = false;
        }

        this.total = {
            solved: 0,
            tried: 0,
            failed: 0,
            points: 0,
            penalty_time: 0,
            last_improvement_time: 0,
        };

        for (var result of this.results) {
            if (result.accepted) {
                this.total.solved += 1;
            }
            
            if (result.attempts > 0) this.total.tried += 1;

            if (result.points > 0) {
                this.total.points += result.points;
                this.total.penalty_time += result.penalty_time;

                this.total.last_improvement_time = Math.max(
                    this.total.last_improvement_time,
                    result.last_submit_time
                );
            }
        }

        this.total.failed = this.total.tried - this.total.solved;
    }
}

class StandingsRenderer {
    constructor (standings) {
        this.standings = standings;
    }

    get mode() {
        return this.standings.mode;
    }

    get show_first_accepted_flag() {
        return this.standings.show_first_accepted_flag;
    }

    get show_testing_flag() {
        return this.standings.show_testing_flag;
    }

    get show_submits_upto_best() {
        return this.standings.show_submits_upto_best;
    }

    get show_last_improvement_column () {
        return this.standings.show_last_improvement_column;
    }

    static get MODE_IOI() { return 0; }
    static get MODE_ICPC() { return 1; }
    static get MODE_GENERAL() { return 2; }

    get_string(str) {
        return this.standings.get_string(str);
    }

    get students_strict_comparator() {
        var loose_comparator = this.students_loose_comparator;

        return function (a, b) {
            var result = loose_comparator(a, b);

            if (result != 0) return result;

            return a.fullname.localeCompare(b.fullname);
        };
    }
    
    get students_loose_comparator() {
        if (this.mode == StandingsRenderer.MODE_IOI) 
            return function (a, b) {
                if (a.total.points > b.total.points) return -1;
                if (a.total.points < b.total.points) return 1;

                return 0;
            };

        if (this.mode == StandingsRenderer.MODE_ICPC) 
            return function (a, b) {
                if (a.total.solved > b.total.solved) return -1;
                if (a.total.solved < b.total.solved) return 1;

                if (a.total.penalty_time < b.total.penalty_time) return -1;
                if (a.total.penalty_time > b.total.penalty_time) return 1;

                if (a.total.last_improvement_time < b.total.last_improvement_time) return -1;
                if (a.total.last_improvement_time > b.total.last_improvement_time) return 1;

                return 0;
            };

        if (this.mode == StandingsRenderer.MODE_GENERAL) 
            return function (a, b) {
                if (a.total.points > b.total.points) return -1;
                if (a.total.points < b.total.points) return 1;

                if (a.total.solved > b.total.solved) return -1;
                if (a.total.solved < b.total.solved) return 1;

                if (a.total.penalty_time < b.total.penalty_time) return -1;
                if (a.total.penalty_time > b.total.penalty_time) return 1;

                if (a.total.last_improvement_time < b.total.last_improvement_time) return -1;
                if (a.total.last_improvement_time > b.total.last_improvement_time) return 1;

                return 0;
            };
        
        throw new Error("Invalid standings rendering mode");
    }

    get show_total_penalty_time_column() {
        if (this.mode == StandingsRenderer.MODE_IOI)     return false;
        if (this.mode == StandingsRenderer.MODE_ICPC)    return true;
        if (this.mode == StandingsRenderer.MODE_GENERAL) return true;
        
        throw new Error("Invalid standings rendering mode");
    }

    get show_total_points_column() {
        if (this.mode == StandingsRenderer.MODE_IOI)     return true;
        if (this.mode == StandingsRenderer.MODE_ICPC)    return false;
        if (this.mode == StandingsRenderer.MODE_GENERAL) return true;
        
        throw new Error("Invalid standings rendering mode");
    }

    with_font_color(inner_html, result) {
        var font_color = result.accepted ? "green" : "red";
        var html = '';
        
        html += '<font color="' + font_color + '" >';
        html += inner_html;
        html += '</font>';

        return html;
    }
    
    with_smaller_font(inner_html) {
        return '<span class="standings-cell-small-text">' + inner_html + '</span>';
    }

    with_results_link(inner_html, result, student) {
        var href = 'results.php?id='+this.standings.course_module_id+'&user_id='+student.user_id+'&task_id='+result.task.task_id+'';

        return '<a href="' + href + '">' + inner_html + '</a>';
    }

    format_as_contest_time(duration) {
        var minutes = duration % 60;
        var hours = Math.floor(duration / 60);
        
        return hours + ':' + Math.floor(minutes / 10) + '' + (minutes % 10);
    }

    part_points(result) {
        var html = '';

        html += result.points;

        return html;
    }

    part_icpc_main(result) {
        var failed_attempts = 0;
        var sign = '';

        if (result.accepted) {
            sign = '+';
            failed_attempts = result.attempts_upto_best-1;
        } else {
            sign = '-';
            failed_attempts = result.attempts;
        }

        return sign + (failed_attempts > 0 ? failed_attempts : '');
    }

    part_submits_count(result) {
        if (this.show_submits_upto_best) {
            return '[' + result.attempts_upto_best + '/' + result.attempts + ']';
        } else {
            return '[' + result.attempts + ']';
        }
    }

    part_penalty_time(result) {
        var duration = (result.points > 0 ? result.best_submit_time : result.last_submit_time);

        return this.format_as_contest_time(duration);
    }

    part_testing_flag(result) {
        if ( !this.show_testing_flag || result.accepted || result.judged ) {
            return '';
        } else {
            return '<sup>?</sup>';
        }
    }

    part_first_accepted_flag_style_class(result) {
        if (this.show_first_accepted_flag && result.is_first_accepted) {
            return 'standings-cell-first-accepted';
        } else {
            return '';
        }
    }

    render_result_ioi(result, student, can_view) {
        var cell_html = '';

        if (result.attempts == 0) return '-';
        
        cell_html += this.part_points(result);
        cell_html += this.part_testing_flag(result);
        
        var submits_html = '<sub>' + this.part_submits_count(result) + '</sub>';
        if (can_view) submits_html = this.with_results_link(submits_html, result, student);
        
        cell_html += submits_html;

        return this.with_font_color(cell_html, result);
    }

    render_result_icpc(result, student, can_view) {
        var up_html = '';
        var down_html = '';

        if (result.attempts == 0) return '-';

        up_html += this.part_icpc_main(result);
        up_html += this.part_testing_flag(result);

        down_html += this.part_penalty_time(result);

        if (can_view) down_html = this.with_results_link(down_html, result, student);

        var final_html = up_html + '<br>' + this.with_smaller_font(down_html);

        return this.with_font_color(final_html, result);
    }
    
    render_result_general(result, student, can_view) {
        var up_html = '';
        var down_html = '';

        if (result.attempts == 0) return '-';
        
        up_html += this.part_points(result);
        up_html += this.part_testing_flag(result);

        down_html += this.part_penalty_time(result);
        down_html += '<br>';
        down_html += this.part_submits_count(result);

        if (can_view) down_html = this.with_results_link(down_html, result, student);

        var final_html = up_html + '<br>' + this.with_smaller_font(down_html);

        return this.with_font_color(final_html, result);
    }

    render_result_cell(result, student, can_view) {
        var inner_html = '';
        
        if      (this.mode == StandingsRenderer.MODE_IOI)     inner_html = this.render_result_ioi(result, student, can_view);
        else if (this.mode == StandingsRenderer.MODE_ICPC)    inner_html = this.render_result_icpc(result, student, can_view);
        else if (this.mode == StandingsRenderer.MODE_GENERAL) inner_html = this.render_result_general(result, student, can_view);
        else throw new Error("Invalid standings rendering mode");
    
        var first_accepted_style =  this.part_first_accepted_flag_style_class(result);

        var cell_html = '';

        cell_html += '<td class="text-center align-middle cell standings-cell ' + first_accepted_style + '" title="' + result.task.letter + '. ' + result.task.name + '" >';
        cell_html += inner_html;
        cell_html += '</td>';

        return cell_html;
    }

    render_student(student) {
        var html = '';

        if (this.standings.hide_inactive && !student.active) {
            return '';
        }

        var can_view = this.standings.has_capability_view_any || (this.standings.moodle_user_id == student.user_id);
        
        html += '<tr>';

        var rank_position_html = '';
        if (student.rank_min_position == student.rank_max_position) {
            rank_position_html = student.rank_min_position;
        } else {
            rank_position_html += student.rank_min_position + '-' + student.rank_max_position;
        }

        html += '<td class="cell text-nowrap text-center">' + rank_position_html + '</td>';

        var can_view_link_html = '';
        if (can_view) {
            can_view_link_html = 
                '<sub>' + 
                    '<a href="results.php?id='+this.standings.course_module_id+'&user_id='+student.user_id+'">' +
                        '[' + this.get_string('submits') + ']' +
                    '</a>' +
                '</sub>'; 
        }

        html += '<td class="cell">' + student.fullname + can_view_link_html + '</td>';

        for (var result of student.results) {
            var cell_html = this.render_result_cell(result, student, can_view);

            html += cell_html;
        }

        html += '<td class="cell text-center">';
        html += '<font color="green">' + student.total.solved + '</font>';
        if (student.total.failed > 0) html += '/' + '<font color="red">' + student.total.failed + '</font>';
        html += '</td>';

        if (this.show_total_points_column) {
            html += '<td class="cell">' + student.total.points + '</td>';
        }
        
        if (this.show_total_penalty_time_column) {
            html += '<td class="cell">';
            html += student.total.penalty_time;
            html += '</td>';
        }

        if (this.show_last_improvement_column) {
            html += '<td class="cell">';
            html += this.format_as_contest_time(student.total.last_improvement_time);
            html += '</td>';
        }

        html += '</tr>';

        return html;
    }

    render_header() {
        var html = '';

        html += '<tr>' +
                '<th class="header text-center" scope="col">N</th>' +
                '<th class="header" scope="col">' + this.get_string('username') + '</th>';
        
        for (var task of this.standings.tasks) {
            html += 
                '<th class="header text-center" scope="col" title="' + task.letter + '. ' + task.name + '">' +
                    task.letter
                '</th>';
        }

        html += '<th class="header text-center" scope="col">+</th>';
        
        if (this.show_total_points_column) {
            html += '<th class="header" scope="col">' + this.get_string('points') + '</th>';
        }
        
        if (this.show_total_penalty_time_column) {
            html += '<th class="header" scope="col">' + this.get_string('penalty') + '</th>';
        }
        
        if (this.show_last_improvement_column) {
            html += '<th class="header" scope="col">' + this.get_string('lastimprovedat') + '</th>';
        }
        
        html += '</tr>';
        
        return html;
    }
}

class Standings {
    constructor (
            students, 
            tasks, 
            submits, 
            course_module_id, 
            moodle_user_id, 
            contest_starttime, 
            contest_endtime, 
            mode, 
            hide_upsolving, 
            hide_inactive, 
            has_capability_view_any, 
            localized_strings
    ) {
        students = students.map(function(st) { return new StandingsStudent(st, tasks); });

        // set up params
        this.students = students;
        this.tasks = tasks;
        this.submits = submits;
        this.course_module_id = course_module_id;
        this.moodle_user_id = moodle_user_id;
        this.contest_starttime = contest_starttime;
        this.contest_endtime = contest_endtime;
        this.mode = mode;
        this.hide_upsolving = hide_upsolving;
        this.hide_inactive = hide_inactive;
        this.has_capability_view_any = has_capability_view_any;
        this.localized_strings = localized_strings;

        // set up default params
        this.show_first_accepted_flag = true;
        this.show_testing_flag = true;
        this.show_submits_upto_best = false;
        this.show_last_improvement_column = false;

        // prepare indexes
        this.student_by_id = {};
        for (var student of students) {
            this.student_by_id[student.user_id] = student;
        }
        
        this.task_by_id = {};
        for (var task of tasks) {
            this.task_by_id[task.task_id] = task;
        }

        // prepare virtual submit times
        for (var submit of submits) {
            if (!this.student_by_id.hasOwnProperty(submit.user_id)) continue;

            var author = this.student_by_id[submit.user_id];

            submit.virtual_submit_time = submit.submit_time - author.start_time_cut;
        }
        
        // server is not guaranteed to give sorted submits array
        submits.sort(function(a, b) {
            if (a.virtual_submit_time != b.virtual_submit_time) {
                return a.virtual_submit_time - b.virtual_submit_time;
            }
            
            return a.id - b.id;
        });
        
        // prepare additional info
        var globally_solved_tasks = new Set();

        for (var submit of this.submits) {
            submit.accepted = (submit.result_id == 13 /* Accepted verdict */);
            submit.judged = (submit.result_id != 1 /* Pending verdict */ && 
                             submit.result_id != 2 /* Running verdict */);
            submit.task = this.task_by_id[submit.task_id];
            submit.is_first_accepted = false;

            submit.points = parseInt(submit.points);
            
            if (submit.accepted && this.student_by_id[submit.user_id]) {
                submit.is_first_accepted = !globally_solved_tasks.has(submit.task_id);

                globally_solved_tasks.add(submit.task_id);
            }
        }

        console.log(submits);

        for (var task of tasks) {
            task.letter = String.fromCharCode('A'.charCodeAt(0)+Number(task.task_order)-1);
        }

        // fill submits
        for (var submit of submits) {
            if (!this.student_by_id.hasOwnProperty(submit.user_id)) continue;
            this.student_by_id[submit.user_id].add_submit(submit);
        }

        // build
        this.build();
    }

    get_string(str) {
        var localized_result = this.localized_strings[str];

        if ( !localized_result) {
            console.error(`String '${str}' is not included into localized_strings_json`);
            return `[[${str}]]`;
        }

        return localized_result;
    }

    toggle_upsolving() {
        this.hide_upsolving = !this.hide_upsolving;

        this.build();

        return this.hide_upsolving;
    }

    toggle_inactive() {
        this.hide_inactive = !this.hide_inactive;

        localStorage.setItem('standings_hide_inactive', this.hide_inactive);

        this.build();

        return this.hide_inactive;
    }

    toggle_show_first_accepted_flag() {
        this.show_first_accepted_flag = !this.show_first_accepted_flag;

        this.build();

        return this.show_first_accepted_flag;
    }

    toggle_show_testing_flag() {
        this.show_testing_flag = !this.show_testing_flag;

        this.build();

        return this.show_testing_flag;
    }

    toggle_show_submits_upto_best() {
        this.show_submits_upto_best = !this.show_submits_upto_best;

        this.build();

        return this.show_submits_upto_best;
    }

    toggle_show_last_improvement_column() {
        this.show_last_improvement_column = !this.show_last_improvement_column;

        this.build();

        return this.show_last_improvement_column;
    }

    set_mode(mode) {
        this.mode = Number(mode);

        this.build();
    }

    build () {
        var renderer = new StandingsRenderer(this);

        // build each line
        for (var student of this.students) {
            student.build_results(
                this.hide_upsolving
            );
        }

        // sort students
        this.students.sort(renderer.students_strict_comparator);

        // assign positions
        var curpos = 0;
        while (curpos < this.students.length) {
            var nextpos = curpos+1;

            while (nextpos < this.students.length && renderer.students_loose_comparator(this.students[curpos], this.students[nextpos]) == 0) {
                nextpos += 1;
            }

            var min_position = curpos + 1;
            var max_position = nextpos;

            for (var i = curpos; i < nextpos; i++) {
                this.students[i].rank_min_position = min_position;
                this.students[i].rank_max_position = max_position;
            }

            curpos = nextpos;
        }

        // prepare html
        for (var student of this.students) {
            student.html = renderer.render_student(student);
        }

        // prepare stats
        this.task_stats = [];
        for (var task of this.tasks) {
            this.task_stats.push({
                task: task,

                solved: 0,
                tried: 0,
            });
        }

        for (var i = 0; i < this.tasks.length; i++) {
            for (var student of this.students) {
                if (student.results[i].accepted) this.task_stats[i].solved += 1;
                if (student.results[i].attempts > 0) this.task_stats[i].tried += 1;
            }
        }
        
        // build
        this.html = '';

        // header
        this.html += '<thead>';
        this.html += renderer.render_header();
        this.html += '</thead>';
        
        this.html += '<tbody>';

        // rows
        for (var student of this.students) {
            this.html += student.html;
        }

        this.html += '</tbody>';

        // stats
        //  stats are common for all modes for now
        this.html += '<tfoot>';
        
        this.html += 
            '<tr>' + 
                '<td></td>' + 
                '<td>' + 
                    '<font color="green">' + this.get_string('amountofaccepted') + '</font>' + 
                    '</br>' + 
                    '<font color="grey">' + this.get_string('amountoftried') + '</font>' + 
                '</td>';

        for (var task_stat of this.task_stats) {
            this.html += '<td class="cell text-center">';

            this.html += 
                '<font color="green" title="' + task_stat.task.letter + '. ' + task_stat.task.name + '">' +
                    task_stat.solved +
                '</font>' +
                '<br>' +
                '<font color="grey" title="' + task_stat.task.letter + '. ' + task_stat.task.name + '">' +
                    task_stat.tried +
                '</font>';

            this.html += '</td>';
        }

        this.html += 
                '<td></td>' +
                '<td></td>' +
                '</tr>' +
            '</tfoot>';

        // show
        document.getElementById('standings_table').innerHTML = this.html;
    }
}