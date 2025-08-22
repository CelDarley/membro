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
	access = create_access_token(identity={'id': user.id, 'role': user.role})
	return {
		'user': { 'id': user.id, 'name': user.name, 'email': user.email, 'role': user.role },
		'token': access,
		'token_type': 'Bearer',
	}

@bp.get('/me')
@jwt_required()
def me():
	ident = get_jwt_identity() or {}
	user = User.query.get(ident.get('id')) if ident else None
	if not user:
		return {'user': None}, 200
	return {'user': { 'id': user.id, 'name': user.name, 'email': user.email, 'role': user.role }} 