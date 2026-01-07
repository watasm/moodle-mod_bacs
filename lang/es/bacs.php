<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Description
 *
 * @package    mod_bacs
 * @copyright  SybonTeam, sybon.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Acciones';
$string['actionswithcontest'] = 'Acciones con el concurso';
$string['add'] = 'Añadir';
$string['advancedcontestsettings'] = 'Configuración avanzada del concurso';
$string['advancedsettingsmessage1'] =
    'Esta sección proporciona acceso directo para realizar operaciones complicadas con los datos del concurso. Por ejemplo, copia rápida de concursos, depuración o configuración de tareas que no están presentes en la base de datos.';
$string['advancedsettingsmessage2'] =
    'Tenga en cuenta que otros componentes no rastrean los cambios en estos campos y los sobrescribirán en la mayoría de las operaciones.';
$string['advancedsettingsmessage3'] =
    'Use esto solo si sabe lo que está haciendo.';
$string['advancedwarning'] = '¡Advertencia!';
$string['allcollections'] = 'Todas las colecciones';
$string['alltasks'] = 'Todas las tareas';
$string['alltasksfrom'] = 'Todas las tareas de';
$string['amountofaccepted'] = 'Cantidad de aceptados';
$string['amountofpretests'] = 'Cantidad de pre-tests';
$string['amountoftests'] = 'Cantidad de tests';
$string['amountoftried'] = 'Cantidad de intentados';
$string['applyfilter'] = 'Aplicar filtro';
$string['author'] = 'Autor';
$string['backtosubmit'] = 'Volver al envío';
$string['backtosubmits'] = 'Volver a los envíos';
$string['bacs:addinstance'] = 'Añadir y eliminar concursos del curso';
$string['bacs:edit'] = 'Cambiar la configuración de cualquier concurso, re-juzgar envíos';
$string['bacs:readtasks'] = 'Ver enunciados completos de todas las tareas del concurso';
$string['bacs:submit'] = 'Enviar soluciones de cualquier tarea del concurso';
$string['bacs:view'] = 'Ver tareas y envíos propios en el concurso';
$string['bacs:viewany'] = 'Ver información detallada sobre cualquier envío en el concurso';
$string['bacsrating:averagerating'] = 'Calificación media de los participantes';
$string['bacsrating:participants'] = 'Participantes';
$string['bacsrating:participantslist'] = 'Lista de participantes';
$string['bacsrating:participant'] = 'Participante';
$string['bacsrating:rated'] = 'Con calificación';
$string['bacsrating:rating'] = 'Puntuación';
$string['bacsrating:sortby'] = 'Ordenar por';
$string['bacsrating:sortby:rating_asc'] = 'Puntuación (ascendente)';
$string['bacsrating:sortby:rating_desc'] = 'Puntuación (descendente)';
$string['beforethecontest'] = 'Antes del concurso';
$string['bright_brighttheme'] = 'Brillante';
$string['cannotviewsubmit'] = 'No se puede ver este envío';
$string['changegrouptosubmit'] = 'Para poder enviar soluciones, debe seleccionar un grupo del que sea miembro.';
$string['charactermustbeadded']   = 'este carácter debe ser añadido';
$string['charactermustberemoved'] = 'este carácter debe ser eliminado';
$string['chartdayhourdistribution'] = 'Gráfico de distribución por día y hora';
$string['chartverdicts'] = 'Gráfico de veredictos';
$string['choosetask'] = 'Elegir tarea';
$string['clear'] = 'Limpiar';
$string['clearform'] = 'Limpiar formulario';
$string['compare'] = 'Comparar';
$string['comparison'] = 'Comparación';
$string['compilermessage'] = 'Mensaje del compilador';
$string['configmaxselectableyear'] = 'Año máximo que se puede seleccionar en la hora de inicio o finalización del concurso en la página de configuración del concurso';
$string['configminselectableyear'] = 'Año mínimo que se puede seleccionar en la hora de inicio o finalización del concurso en la página de configuración del concurso';
$string['configpreferedlanguage'] = 'Idiomas predeterminados para la visualización de enunciados de tareas';
$string['preferedlanguage'] = 'Idiomas preferidos';
$string['configsybonapikey'] = 'La clave API de Sybon se utiliza para enviar envíos, obtener idiomas y obtener tareas';
$string['contesthasstartednotification'] = 'El concurso ha comenzado. ¿Quieres entrar al concurso?';
$string['contestmode'] = 'Modo de concurso';
$string['contestname'] = 'Nombre del concurso';
$string['contestsettings'] = 'Configuración del concurso';
$string['contesttasks'] = 'Tareas del concurso';
$string['count'] = 'Cuenta';
$string['course'] = 'Curso';
$string['coverssametests'] = 'cubre las mismas pruebas que el nuevo grupo';
$string['dark_darktheme'] = 'Oscuro';
$string['dashboard'] = 'Tablero';
$string['dateandtime'] = 'Fecha y hora';
$string['days_morethanxdays'] = 'días';
$string['default_defaulttheme'] = 'Predeterminado';
$string['delete'] = 'Eliminar';
$string['detectincidents'] = 'Detectar incidentes';
$string['devirtualize'] = 'Devirtualizar';
$string['devirtualizewarning'] =
    '¿Estás seguro de que quieres eliminar la participación virtual? Los envíos de los usuarios NO se eliminarán. Los datos de participación virtual no se pueden restaurar.';
$string['diagnostics:check'] = 'Comprobar';
$string['diagnostics:deprecated_tasks_msg'] = 'Comprobación de tareas obsoletas. Tareas obsoletas disponibles: {$a}';
$string['diagnostics:duplicate_tasks_msg'] = 'Comprobación de tareas duplicadas. {$a->tasks_to_be_replaced} tareas para reemplazar / {$a->tasks_without_replacement} duplicados sin reemplazo / {$a->tasks_with_the_same_name} tareas con el mismo nombre';
$string['diagnostics:duration'] = 'Duración';
$string['diagnostics:error'] = 'Error';
$string['diagnostics:message'] = 'Mensaje';
$string['diagnostics:milliseconds_short'] = 'ms';
$string['diagnostics:ok'] = 'OK';
$string['diagnostics:showdetailedlogs'] = 'Mostrar registros detallados';
$string['diagnostics:status'] = 'Estado';
$string['diagnostics:sybon_api_collections_msg'] = 'Comprobación de la API de tareas de Sybon. Colecciones de tareas disponibles: {$a}';
$string['diagnostics:sybon_api_compilers_msg'] = 'Comprobación de la API de compiladores de Sybon. Compiladores/idiomas disponibles: {$a}';
$string['diagnostics:sybon_api_submits_msg'] = 'Comprobación de la API de envíos de Sybon. ID de Sybon del envío de prueba: {$a}';
$string['diagnostics:sybon_api_submits_msg_no_submits'] = 'Comprobación de la API de envíos de Sybon: no hay envíos disponibles. Debe haber al menos un envío (no "Pendiente") disponible para comprobar la API de envíos de Sybon';
$string['diagnostics:task_pretests_msg'] = 'Comprobación de pre-tests de tareas. {$a->tasks_in_total} tareas en total / {$a->tasks_with_wrong_pretests_count} tareas con un número incorrecto de pre-tests / {$a->tasks_with_wrong_pretests_index} tareas con numeración incorrecta de pre-tests / {$a->tasks_without_pretests} tareas sin pre-tests';
$string['diagnostics:task_statement_format_msg'] = 'Comprobación del formato del enunciado de la tarea. {$a->with_doc} en DOC(DOCX) / {$a->with_pdf} en PDF / {$a->with_html} en HTML / {$a->with_other_format} en otros formatos';
$string['diagnostics:test_points_strings_msg'] = 'Comprobación de la configuración de las tareas en los concursos. {$a->records_in_total} registros en total / {$a->records_with_custom_points} con puntos personalizados / {$a->records_with_missing_task} con tarea faltante / {$a->records_mismatched} con puntos no coincidentes';
$string['diagnostics:warning'] = 'Advertencia';
$string['download'] = 'Descargar';
$string['duplicatetasks'] = '¡No se permiten tareas duplicadas!';
$string['editortheme'] = 'Tema del editor';
$string['endtime'] = 'Hora de finalización';
$string['entercomment'] = 'Introducir comentario:';
$string['entercontest'] = 'Entrar al concurso';
$string['entercontestwithoutvirtual'] = 'Entrar al concurso sin participación virtual';
$string['enterpoints'] = 'Introducir puntos:';
$string['enterverdict'] = 'Introducir veredicto:';
$string['errordeletingtask'] = "Error al eliminar la tarea con id=";
$string['fillwithintegers'] = "Todos los campos deben rellenarse con números enteros no negativos.";
$string['format'] = 'Formato';
$string['from'] = 'Hora de inicio del concurso';
$string['fromshort'] = 'Desde';
$string['futurepointsnotification'] = 'Tenga en cuenta que cambiar los puntos de la prueba no tendrá ningún efecto en los envíos anteriores. Debe hacer clic en "Recalcular puntos" en el menú "Acciones" para forzar que los envíos anteriores sean juzgados con los nuevos puntos de la prueba.';
$string['generalnopermission'] = '¡No tienes permiso para esta operación!';
$string['gotogroupsettings'] = 'Ir a la configuración especial del grupo';
$string['groupname'] = 'Nombre del grupo';
$string['groupsettingsarenotused'] = 'No se utiliza la configuración especial del grupo';
$string['groupsettingsareused'] = '{$a->with_group_settings} de {$a->total_count} grupos están utilizando configuraciones especiales';
$string['hideinactive'] = 'Ocultar inactivos';
$string['hidesolution'] = 'Ocultar solución';
$string['hideupsolving'] = 'Ocultar resolución posterior';
$string['id'] = 'ID';
$string['incident'] = 'Incidente';
$string['incidentdetectiondisabledalert'] = 'La detección automática de incidentes para este concurso está deshabilitada. Habilite "Detectar incidentes" en la sección "Incidentes" en la configuración del concurso.';
$string['incidents'] = 'Incidentes';
$string['incidentssettings'] = 'Configuración de incidentes';
$string['input'] = 'Entrada';
$string['invalidrange'] = "¡El rango no es válido!";
$string['isolatedparticipants'] = 'Participantes aislados';
$string['isolateparticipants'] = 'Aislar participantes';
$string['language'] = 'Idioma';
$string['lastimprovedat'] = 'Mejorado en';
$string['letterlimit26'] = 'La lista está limitada a un máximo de 26 letras. No se permite añadir más.';
$string['letterlistempty'] = "Error al eliminar la última letra: la lista de letras está vacía";
$string['linktothissubmission'] = 'Enlace a este envío';
$string['load_from_file'] = 'Cargar desde archivo';
$string['maximumtasks26'] = "El concurso está limitado a un máximo de 26 tareas. No se permite añadir más.";
$string['maxselectableyear'] = 'Año máximo seleccionable';
$string['method'] = 'Método';
$string['memory'] = 'Memoria';
$string['memorylimit'] = 'Límite de memoria';
$string['minselectableyear'] = 'Año mínimo seleccionable';
$string['modulename'] = 'Concurso BACS';
$string['modulename_help'] = 'bacs es el complemento que hace algo. Claro, es mejor que nada';
$string['modulenameplural'] = 'Concursos BACS';
$string['more'] = 'Más';
$string['morethan'] = 'más que';
$string['mysubmits'] = 'Mis envíos';
$string['n'] = 'N';
$string['negativepointsnotallowed'] = "No se permiten puntos negativos";
$string['nopermissiontosubmit'] = 'No tienes permiso para enviar soluciones.';
$string['not_found'] = '¡No encontrado!';
$string['not_started'] = '¡El concurso no ha comenzado!';
$string['open'] = 'Abrir';
$string['outputexpected'] = 'Salida esperada';
$string['outputreal'] = 'Salida real';
$string['penalty'] = "Penalización";
$string['pluginadministration'] = 'Configuración de BACS';
$string['plugindiagnosticspage'] = 'Página de diagnóstico del complemento';
$string['pluginname'] = 'Concursos BACS';
$string['points'] = "Puntos";
$string['pointsforfullsolution'] = 'Puntos por solución completa';
$string['pointsformissingtask'] = "No se pueden cargar los puntos de la prueba para una tarea faltante.";
$string['pointspergroup'] = 'Puntos por grupo';
$string['pointspertest'] = 'Puntos por prueba';
$string['presolving'] = 'Permitir la resolución de problemas antes del comienzo del concurso';
$string['pretest'] = 'Pre-test';
$string['privacy:metadata:bacs'] = 'Almacena información sobre concursos y clasificaciones';
$string['privacy:metadata:bacs:standings'] = 'Información almacenada en caché JSON sobre todos los envíos que se muestran en las clasificaciones';
$string['privacy:metadata:bacs_group_info'] = 'Almacena configuraciones especiales para grupos y clasificaciones de grupos';
$string['privacy:metadata:bacs_group_info:standings'] = 'Información almacenada en caché JSON sobre todos los envíos que se muestran en las clasificaciones de grupo';
$string['privacy:metadata:bacs_submits'] = 'Almacena información de los envíos';
$string['privacy:metadata:bacs_submits:contest_id'] = 'ID del concurso donde se envió el envío';
$string['privacy:metadata:bacs_submits:group_id'] = 'ID del grupo al que pertenece el envío dado (o cero si no se usó el grupo)';
$string['privacy:metadata:bacs_submits:info'] = 'Mensaje del compilador o información especial';
$string['privacy:metadata:bacs_submits:lang_id'] = 'ID del lenguaje de programación';
$string['privacy:metadata:bacs_submits:max_memory_used'] = 'Memoria máxima utilizada en todas las pruebas en bytes';
$string['privacy:metadata:bacs_submits:max_time_used'] = 'Tiempo máximo utilizado en todas las pruebas en milisegundos';
$string['privacy:metadata:bacs_submits:points'] = 'Puntos';
$string['privacy:metadata:bacs_submits:result_id'] = 'Resultado del juicio';
$string['privacy:metadata:bacs_submits:source'] = 'Código fuente';
$string['privacy:metadata:bacs_submits:submit_time'] = 'Hora en que se creó el envío';
$string['privacy:metadata:bacs_submits:task_id'] = 'ID de la tarea';
$string['privacy:metadata:bacs_submits:test_num_failed'] = 'Número de la primera prueba fallida';
$string['privacy:metadata:bacs_submits:user_id'] = 'Autor del envío';
$string['privacy:metadata:bacs_submits_tests'] = 'Almacena información sobre todas las ejecuciones de envío en cada prueba';
$string['privacy:metadata:bacs_submits_tests:memory_used'] = 'Memoria utilizada en bytes';
$string['privacy:metadata:bacs_submits_tests:status_id'] = 'Resultado del juicio';
$string['privacy:metadata:bacs_submits_tests:submit_id'] = 'ID del envío';
$string['privacy:metadata:bacs_submits_tests:test_id'] = 'ID de la prueba';
$string['privacy:metadata:bacs_submits_tests:time_used'] = 'Tiempo utilizado en milisegundos';
$string['privacy:metadata:bacs_submits_tests_output'] = 'Almacena las salidas de los envíos en las pre-pruebas';
$string['privacy:metadata:bacs_submits_tests_output:output'] = 'Salida del envío';
$string['privacy:metadata:bacs_submits_tests_output:submit_id'] = 'ID del envío';
$string['privacy:metadata:bacs_submits_tests_output:test_id'] = 'ID de la prueba';
$string['privacy:metadata:sybon_checking_service'] = 'Se utiliza para ejecutar soluciones y obtener resultados de juicio';
$string['privacy:metadata:sybon_checking_service:lang_id'] = 'ID del lenguaje de programación';
$string['privacy:metadata:sybon_checking_service:source'] = 'Código fuente';
$string['privacy:metadata:sybon_checking_service:task_id'] = 'ID de la tarea';
$string['privacy:metadata:sybon_checking_service:timestamp'] = 'Hora en que se pasó el envío a juicio';
$string['prog_lang'] = 'Lenguaje de programación';
$string['programcode'] = 'Código del programa';
$string['rawcontesttaskids'] = 'Cadena de ID de tareas del concurso codificada en bruto';
$string['rawcontesttasktestpoints'] = 'Cadena de puntos de prueba de tareas del concurso codificada en bruto';
$string['recalcpoints'] = 'Recalcular puntos';
$string['recalculateincidents'] = 'Recalcular incidentes';
$string['recalculatepoints'] = 'Recalcular puntos';
$string['recalculatepointsfor'] = 'Recalcular puntos para:';
$string['rejectsubmit'] = 'Rechazar envío';
$string['rejudge'] = 'Re-juzgar';
$string['rejudgesubmits'] = 'Re-juzgar envíos';
$string['rejudgesubmitsfor'] = 'Re-juzgar envíos para:';
$string['rememberlanguage'] = 'Recordar el idioma elegido';
$string['result'] = 'Resultado';
$string['resultsgraph'] = 'Gráfico de resultados';
$string['search'] = 'Buscar';
$string['seconds_short'] = 's';
$string['send'] = 'Enviar';
$string['sendforjudgement'] = 'Enviar solución para juicio';
$string['sendinginprogress'] = 'Envío en curso';
$string['sentat'] = 'Enviado en';
$string['setcomment'] = 'Establecer comentario';
$string['setpoints'] = 'Establecer puntos';
$string['settings'] = 'Configuración';
$string['setverdict'] = 'Establecer veredicto';
$string['showfirstacceptedflag'] = 'Mostrar bandera de primer aceptado';
$string['showincidentflags'] = 'Mostrar incidentes';
$string['showlastimprovementcolumn'] = 'Mostrar columna de última mejora';
$string['showsolution'] = 'Mostrar solución';
$string['showsubmitsfor'] = 'Mostrar envíos para';
$string['showsubmitsuptobest'] = 'Mostrar envíos hasta el mejor';
$string['showtestingflag'] = 'Mostrar bandera de prueba';
$string['showupsolving'] = 'Mostrar resolución posterior';
$string['source'] = 'Código fuente';
$string['standings'] = 'Clasificación';
$string['standingsmode'] = 'Modo de clasificación';
$string['standingssettings'] = 'Configuración de la clasificación';
$string['starttime'] = 'Hora de inicio';
$string['startvirtualparticipationnow'] = 'Iniciar participación virtual ahora';
$string['statement'] = 'Enunciado';
$string['status'] = 'Estado';
$string['statusfrozen'] = 'Congelado';
$string['statusnotstarted'] = 'No comenzado';
$string['statusover'] = 'Terminado';
$string['statusrunning'] = 'En curso';
$string['statusunknown'] = 'Desconocido';
$string['submissionsspampenalty'] =
    "¡Has enviado demasiados envíos! Has enviado 50 envíos en los últimos 5 minutos. El envío de soluciones está temporalmente prohibido. Intenta recargar esta página más tarde.";
$string['submissionsspamwarning'] =
    "¡Estás enviando demasiados envíos! Si envías 50 envíos en 5 minutos, perderás temporalmente la capacidad de enviar soluciones.";
$string['submit_verdict_0'] = "Desconocido";
$string['submit_verdict_1'] = "Pendiente";
$string['submit_verdict_10'] = "Límite de salida excedido";
$string['submit_verdict_11'] = "Error de presentación";
$string['submit_verdict_12'] = "Respuesta incorrecta";
$string['submit_verdict_13'] = "Aceptado";
$string['submit_verdict_14'] = "Solicitud incorrecta";
$string['submit_verdict_15'] = "Datos insuficientes";
$string['submit_verdict_16'] = "Límite de consultas excedido";
$string['submit_verdict_17'] = "Datos en exceso";
$string['submit_verdict_18'] = "Envío rechazado";
$string['submit_verdict_2'] = "Ejecutando";
$string['submit_verdict_3'] = "Error del servidor";
$string['submit_verdict_4'] = "Error de compilación";
$string['submit_verdict_5'] = "Error de ejecución";
$string['submit_verdict_6'] = "Prueba fallida";
$string['submit_verdict_7'] = "Límite de tiempo de CPU excedido";
$string['submit_verdict_8'] = "Límite de tiempo real excedido";
$string['submit_verdict_9'] = "Límite de memoria excedido";
$string['submitmessagetaskismissing'] =
    'Esta tarea falta en la base de datos de Moodle. Elimine esta tarea de este concurso o actualice la información sobre las tareas disponibles.';
$string['submits'] = 'Envíos';
$string['submitsfrom'] = 'Envíos de';
$string['submitslowercase'] = 'envíos';
$string['sumofpoints'] = 'Suma de puntos';
$string['sybonapikey'] = 'Clave API de Sybon';
$string['task'] = 'Tarea';
$string['taskdynamics'] = 'Dinámica de la tarea';
$string['taskid'] = 'ID de la tarea';
$string['tasklist'] = 'Lista de tareas';
$string['taskname'] = 'Nombre de la tarea';
$string['taskofsubmitismissingincontest'] = '
    La tarea (ID {$a->taskid}) de este envío falta en el concurso actual.
    Debes volver a añadir esta tarea al concurso si quieres que este envío se muestre correctamente.';
$string['taskofsubmitismissingincontestanddb'] = '
    La tarea (ID {$a->taskid}) de este envío falta tanto en este concurso como en la base de datos de Moodle.
    Debes actualizar la información sobre las tareas disponibles y volver a añadir esta tarea al concurso si quieres que este envío se muestre correctamente.';
$string['tasks'] = 'Tareas';
$string['test'] = 'Prueba';
$string['testgroup'] = 'Grupo de prueba';
$string['testpoints'] = 'Puntos de la prueba';
$string['tests'] = 'Pruebas';
$string['therearenoresults'] = 'No hay resultados';
$string['time'] = 'Tiempo';
$string['timelimit'] = 'Límite de tiempo';
$string['to'] = 'Hora de finalización del concurso';
$string['toshort'] = 'Hasta';
$string['totalincidents'] = 'Total de incidentes de comportamiento sospechoso';
$string['updatestandings'] = 'Actualizar clasificación';
$string['uppercaselanguagenotfound'] = 'IDIOMA NO ENCONTRADO';
$string['uppercasetasknotfound'] = 'TAREA NO ENCONTRADA';
$string['upsolving'] = 'Permitir resolución posterior';
$string['upsolving_help'] = 'A los estudiantes se les permitirá enviar tareas después del final del concurso. Los resultados de la resolución posterior se mostrarán por separado.';
$string['upsolvingisdisabled'] = 'La resolución posterior está deshabilitada para este concurso.';
$string['usecustomtestpoints'] = 'Usar puntos de prueba personalizados';
$string['usegroupsettings'] = 'Usar configuración especial para este grupo';
$string['userdynamics'] = 'Dinámica del usuario';
$string['username'] = 'Nombre de usuario';
$string['verdict'] = 'Veredicto';
$string['virtualparticipants'] = 'Participantes virtuales';
$string['virtualparticipantslist'] = 'Lista de participantes virtuales';
$string['virtualparticipantslistisempty'] = 'La lista de participantes virtuales está vacía.';
$string['virtualparticipation'] = 'Participación virtual';
$string['virtualparticipationallow'] = 'Permitir participación virtual';
$string['virtualparticipationallowmsg'] = 'La participación virtual está disponible en este concurso. La participación virtual estará disponible después del inicio del concurso.';
$string['virtualparticipationalreadyhavesubmits'] = 'No puedes iniciar la participación virtual porque ya tienes envíos en este concurso.';
$string['virtualparticipationconfirmstartdmsg'] = '¿Estás seguro de que quieres iniciar la participación virtual ahora? No podrás cancelar la participación virtual después de comenzar.';
$string['virtualparticipationdisable'] = 'Deshabilitar la participación virtual';
$string['virtualparticipationdisabledmsg'] = 'La participación virtual está deshabilitada en este concurso.';
$string['virtualparticipationgeneralwarning'] = '
    La participación virtual es la forma de participar en el concurso en un momento independiente.
    Los resultados de todos los usuarios se muestran en relación con sus diferentes horas de inicio.
    Si ya participaste en este concurso o si ya has visto las tareas de este concurso, deberías optar por la resolución posterior.
    <br><br>
    <b>¡Advertencia!</b> Cada usuario puede iniciar la participación virtual solo una vez. Si tienes algún envío no rechazado en este concurso, no puedes participar virtualmente.';
$string['virtualparticipationonly'] = 'Solo participación virtual';
$string['virtualparticipationonlymsg'] = 'Este concurso es solo de participación virtual. La participación virtual estará disponible después del inicio del concurso.';
$string['virtualparticipationselectyourgroup'] = 'Debes seleccionar tu grupo para iniciar la participación virtual.';
$string['virtualparticipationstartedat'] = 'Tu participación virtual comenzó en';
$string['no_plugin_installed'] = 'El complemento bacs_rating no está instalado. No puedes usar esta funcionalidad';
$string['difficultyanalysis'] = 'Análisis de la Dificultad del Concurso';
$string['analyzecontestdifficulty'] = 'Analizar La Dificultad del Concurso';
$string['notasksselected'] = 'No hay tareas seleccionadas. Por favor, agregue tareas al concurso primero.';
$string['difficulty_analysis_students_can_solve'] = 'Estudiantes que pueden resolver la tarea';
$string['difficulty_analysis_ideal_curve'] = 'Curva ideal';
$string['difficulty_analysis_number_of_students'] = 'Número de estudiantes';
$string['difficulty_analysis_tasks'] = 'Tareas';