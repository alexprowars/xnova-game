<table class="table">
	<tr>
		<td class="c" colspan="6">{{ parse['title'] }}</td>
	</tr>
	<tr>
		<td class="c">&nbsp;</td>
		<td class="c">Пользователь</td>
		<td class="c">Альянс</td>
		<td class="c">Координаты</td>
		<td class="c">Текст</td>
		<td class="c">&nbsp;</td>
	</tr>
	{% if parse['list']|length %}
		{% for id, list in parse['list'] %}
			<tr>
				<th width="20">{{ id + 1 }}</th>
				<th><a href="{{ url('messages/write/'~list['userid']~'') }}">{{ list['username'] }}</a></th>
				<th>{{ list['ally'] }}</th>
				<th><a href="{{ url('galaxy/'~list['g']~'/'~list['s']~'/') }}">{{ list['g'] }}:{{ list['s'] }}:{{ list['p'] }}</a></th>
				<th>{{ list['online'] }}</th>
				<th>
					{% if parse['isMy'] %}
						<a href="{{ url('buddy/delete/'~list['id']~'/') }}">Удалить запрос</a>
					{% else %}
						<a href="{{ url('buddy/approve/'~list['id']~'/') }}">Применить</a>
						<br/>
						<a href="{{ url('buddy/delete/'~list['id']~'/') }}">Отклонить</a>
					{% endif %}
				</th>
			</tr>
		{% endfor %}
	{% else %}
		<tr>
			<th colspan="6">Нет друзей</th>
		</tr>
	{% endif %}
	<tr>
		<td colspan="6" class="c"><a href="{{ url('buddy/') }}">назад</a></td>
	</tr>
</table>