from flask import Blueprint, render_template

bp = Blueprint('views', __name__)

@bp.get('/login')
def login_page():
	return render_template('login.html')

@bp.get('/')
def members_page():
	return render_template('membros.html') 