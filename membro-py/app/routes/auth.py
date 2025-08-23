from flask import Blueprint, request
from flask_jwt_extended import create_access_token, jwt_required, get_jwt_identity
from ..db import db
from ..models import User

bp = Blueprint('auth', __name__)

@bp.post('/login')
def login():
	data = request.get_json(force=True)
	email = (data.get('email') or '').strip().lower()
	password = data.get('password') or ''
	user = User.query.filter_by(email=email).first()
	if not user or not user.check_password(password):
		return {'message': 'Credenciais inválidas'}, 401
	# identity deve ser string no Flask-JWT-Extended v4; incluir role em additional_claims
	access = create_access_token(identity=str(user.id), additional_claims={'role': user.role})
	return {
		'user': { 'id': user.id, 'name': user.name, 'email': user.email, 'role': user.role },
		'token': access,
		'token_type': 'Bearer',
	}

@bp.get('/me')
@jwt_required()
def me():
	ident = get_jwt_identity()
	user = User.query.get(int(ident)) if ident else None
	if not user:
		return {'user': None}, 200
	return {'user': { 'id': user.id, 'name': user.name, 'email': user.email, 'role': user.role }}

@bp.post('/change-password')
@jwt_required()
def change_password():
	ident = get_jwt_identity()
	user = User.query.get(int(ident)) if ident else None
	if not user:
		return { 'message': 'Usuário não encontrado' }, 404
	data = request.get_json(force=True) or {}
	current = (data.get('current_password') or '').strip()
	new = (data.get('new_password') or '').strip()
	confirm = (data.get('confirm') or '').strip()
	if not current or not new:
		return { 'message': 'Informe a senha atual e a nova senha' }, 422
	if new != confirm:
		return { 'message': 'Confirmação de senha não confere' }, 422
	if not user.check_password(current):
		return { 'message': 'Senha atual incorreta' }, 422
	user.set_password(new)
	db.session.commit()
	return { 'success': True } 