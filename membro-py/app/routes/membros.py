from flask import Blueprint, request
from flask_jwt_extended import jwt_required
from sqlalchemy import func
from ..db import db
from ..models import Membro

bp = Blueprint('membros', __name__)


def apply_filters(query):
	q = (request.args.get('q') or '').strip()
	if q:
		like = f"%{q}%"
		query = query.filter(
			(Membro.nome.ilike(like)) | (Membro.comarca_lotacao.ilike(like)) | (Membro.cargo_efetivo.ilike(like))
		)
	filters_json = request.args.get('filters_json')
	# para simplificar: ignorado por enquanto
	return query


def to_row(m: Membro):
	return {
		'id': m.id,
		'data': {
			'Membro': m.nome,
			'Sexo': m.sexo,
			'Concurso': m.concurso,
			'Cargo efetivo': m.cargo_efetivo,
			'Titularidade': m.titularidade,
			'eMail pessoal': m.email_pessoal,
			'Cargo Especial': m.cargo_especial,
			'Telefone Unidade': m.telefone_unidade,
			'Telefone celular': m.telefone_celular,
			'Unidade Lotação': m.unidade_lotacao,
			'Comarca Lotação': m.comarca_lotacao,
			'Time de futebol e outros grupos extraprofissionais': m.time_extraprofissionais,
			'Quantidade de filhos': m.quantidade_filhos,
			'Nome dos filhos': m.nomes_filhos,
			'Estado de origem': m.estado_origem,
			'Acadêmico': m.academico,
			'Pretensão de movimentação na carreira': m.pretensao_carreira,
			'Carreira anterior': m.carreira_anterior,
			'Liderança': m.lideranca,
			'Grupos identitários': m.grupos_identitarios,
			'Amigos no MP (IDs)': [a.id for a in m.amigos],
		}
	}


@bp.get('/membros')
@jwt_required()
def list_membros():
	query = apply_filters(Membro.query)
	page = int(request.args.get('page', 1))
	per_page = int(request.args.get('per_page', 20))
	p = query.order_by(Membro.id.desc()).paginate(page=page, per_page=per_page, error_out=False)
	data = [to_row(m) for m in p.items]
	return {'data': data, 'total': p.total}


@bp.get('/membros/<int:id>')
@jwt_required()
def get_membro(id: int):
	m = Membro.query.get_or_404(id)
	return to_row(m)


@bp.get('/membros/aggregate')
@jwt_required()
def aggregate_membros():
	field = (request.args.get('field') or '').strip()
	col = {
		'Comarca Lotação': Membro.comarca_lotacao,
		'Cargo efetivo': Membro.cargo_efetivo,
	}.get(field)
	if not col:
		return {'field': field, 'data': []}
	query = apply_filters(Membro.query)
	rows = query.with_entities(col.label('v'), func.count(Membro.id).label('c')).group_by(col).order_by(func.count(Membro.id).desc()).limit(int(request.args.get('limit', 50))).all()
	data = [ {'v': r.v, 'c': int(r.c)} for r in rows if r.v ]
	return {'field': field, 'data': data}


@bp.get('/membros/stats')
@jwt_required()
def stats_membros():
	query = apply_filters(Membro.query)
	total = query.count()
	female = query.filter(Membro.sexo == 'Feminino').count()
	pct = round((female * 100 / total), 1) if total else 0.0
	return {'total': total, 'female_count': female, 'female_pct': pct}


@bp.post('/membros')
@jwt_required()
def create_membro():
	data = (request.get_json() or {}).get('data') or {}
	m = Membro(
		nome=data.get('Membro') or data.get('Nome'),
		sexo=data.get('Sexo'),
		concurso=data.get('Concurso'),
		cargo_efetivo=data.get('Cargo efetivo'),
		titularidade=data.get('Titularidade'),
		email_pessoal=data.get('eMail pessoal'),
		cargo_especial=data.get('Cargo Especial'),
		telefone_unidade=data.get('Telefone Unidade'),
		telefone_celular=data.get('Telefone celular'),
		unidade_lotacao=data.get('Unidade Lotação'),
		comarca_lotacao=data.get('Comarca Lotação'),
		time_extraprofissionais=data.get('Time de futebol e outros grupos extraprofissionais'),
		quantidade_filhos=(int(data.get('Quantidade de filhos')) if data.get('Quantidade de filhos') not in (None, '') else None),
		nomes_filhos=data.get('Nome dos filhos'),
		estado_origem=(data.get('Estado de origem')[:2] if data.get('Estado de origem') else None),
		academico=data.get('Acadêmico'),
		pretensao_carreira=data.get('Pretensão de movimentação na carreira'),
		carreira_anterior=data.get('Carreira anterior'),
		lideranca=data.get('Liderança'),
		grupos_identitarios=data.get('Grupos identitários'),
	)
	db.session.add(m)
	db.session.commit()
	# amigos posteriormente
	return {'success': True, 'id': m.id}


@bp.put('/membros/<int:id>')
@jwt_required()
def update_membro(id: int):
	m = Membro.query.get_or_404(id)
	data = (request.get_json() or {}).get('data') or {}
	m.nome = data.get('Membro') or data.get('Nome') or m.nome
	m.sexo = data.get('Sexo') or m.sexo
	m.concurso = data.get('Concurso') or m.concurso
	m.cargo_efetivo = data.get('Cargo efetivo') or m.cargo_efetivo
	m.titularidade = data.get('Titularidade') or m.titularidade
	m.email_pessoal = data.get('eMail pessoal') or m.email_pessoal
	m.cargo_especial = data.get('Cargo Especial') or m.cargo_especial
	m.telefone_unidade = data.get('Telefone Unidade') or m.telefone_unidade
	m.telefone_celular = data.get('Telefone celular') or m.telefone_celular
	m.unidade_lotacao = data.get('Unidade Lotação') or m.unidade_lotacao
	m.comarca_lotacao = data.get('Comarca Lotação') or m.comarca_lotacao
	m.time_extraprofissionais = data.get('Time de futebol e outros grupos extraprofissionais') or m.time_extraprofissionais
	m.quantidade_filhos = (int(data.get('Quantidade de filhos')) if data.get('Quantidade de filhos') not in (None, '') else m.quantidade_filhos)
	m.nomes_filhos = data.get('Nome dos filhos') or m.nomes_filhos
	m.estado_origem = (data.get('Estado de origem')[:2] if data.get('Estado de origem') else m.estado_origem)
	m.academico = data.get('Acadêmico') or m.academico
	m.pretensao_carreira = data.get('Pretensão de movimentação na carreira') or m.pretensao_carreira
	m.carreira_anterior = data.get('Carreira anterior') or m.carreira_anterior
	m.lideranca = data.get('Liderança') or m.lideranca
	m.grupos_identitarios = data.get('Grupos identitários') or m.grupos_identitarios
	db.session.commit()
	return {'success': True} 