<form action="{{ url('notes/new/') }}" method="post">
	<table class="table">
		<tr>
			<td class="c" colspan="2">{{ _text('Createnote') }}</td>
		</tr>
		<tr>
			<th>Приоритет:
				<select name="u" title="">
					<option value="2" selected>{{ _text('Important') }}</option>
					<option value="1">{{ _text('Normal') }}</option>
					<option value="0">{{ _text('Unimportant') }}</option>
				</select>
			</th>
			<th>Тема:
				<input type="text" name="title" size="30" maxlength="30" value="" placeholder="Введите тему">
			</th>
		</tr>
		<tr>
			<th colspan="2" class="p-a-0">
				<div id="editor"></div>
				<textarea name="text" id="text" rows="10" placeholder="Введите текст"></textarea>
				<script type="text/javascript">edToolbar('text');</script>
			</th>
		</tr>
		<tr>
			<td class="c" colspan="2">
				<input type="reset" value="{{ _text('Reset') }}">
				<input type="submit" value="{{ _text('Save') }}">
			</td>
		</tr>
	</table>
	<div id="showpanel" style="display:none">
		<table align="center" width='651'>
			<tr>
				<td class="c"><b>Предварительный просмотр</b></td>
			</tr>
			<tr>
				<td class="b"><span id="showbox"></span></td>
			</tr>
		</table>
	</div>
</form>
<span style="float:left;margin-left: 10px;margin-top: 10px;"><a href="{{ url('notes/') }}">Назад</a></span>