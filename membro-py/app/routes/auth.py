from flask import Blueprint, request
from flask_jwt_extended import create_access_token, jwt_required, get_jwt_identity
from ..db import db
from ..models import User
from datetime import datetime, timedelta
import os, random, string, logging

bp = Blueprint('auth', __name__)
logger = logging.getLogger(__name__)

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

@bp.post('/forgot-password')
def forgot_password():
	data = request.get_json(force=True) or {}
	email = (data.get('email') or '').strip().lower()
	if not email:
		return { 'message': 'Informe o e-mail' }, 422
	user = User.query.filter_by(email=email).first()
	# Não revelar se existe ou não. Sempre responder ok.
	code = ''.join(random.choices(string.digits, k=6))
	if user:
		user.reset_code = code
		user.reset_expires_at = datetime.utcnow() + timedelta(minutes=15)
		db.session.commit()
		logger.info('Código de reset para %s: %s (válido por 15 min)', email, code)
		# TODO: se SMTP configurado, enviar e-mail real
	return { 'success': True }

@bp.post('/reset-password')
def reset_password():
	data = request.get_json(force=True) or {}
	email = (data.get('email') or '').strip().lower()
	code = (data.get('code') or '').strip()
	new = (data.get('new_password') or '').strip()
	confirm = (data.get('confirm') or '').strip()
	if not email or not code or not new:
		return { 'message': 'Dados incompletos' }, 422
	if new != confirm:
		return { 'message': 'Confirmação de senha não confere' }, 422
	user = User.query.filter_by(email=email).first()
	if not user or not user.reset_code or not user.reset_expires_at:
		return { 'message': 'Código inválido' }, 422
	if user.reset_code != code:
		return { 'message': 'Código inválido' }, 422
	if datetime.utcnow() > user.reset_expires_at:
		return { 'message': 'Código expirado' }, 422
	user.set_password(new)
	user.reset_code = None
	user.reset_expires_at = None
	db.session.commit()
	return { 'success': True } 